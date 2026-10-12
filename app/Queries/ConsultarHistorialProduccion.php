<?php

namespace App\Queries;

use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConsultarHistorialProduccion
{
    public function ejecutar(array $filtros): LengthAwarePaginator
    {
        $modelos = ['pan' => DetallePan::class, 'torta' => DetalleTorta::class, 'bocadito' => DetalleBocadito::class];
        $union = null;
        foreach ($modelos as $familia => $modelo) {
            if (! empty($filtros['categoria']) && $filtros['categoria'] !== $familia) {
                continue;
            }
            $tabla = (new $modelo)->getTable();
            $consulta = DB::table($tabla.' as detalle')
                ->join('produccion', 'produccion.id', '=', 'detalle.produccion_id')
                ->join('categorias', 'categorias.id', '=', 'produccion.categoria_id')
                ->where('categorias.nombre_categorias', ucfirst($familia))
                ->select('detalle.id', 'produccion.fecha')
                ->selectRaw('? as familia', [$familia]);
            if (! empty($filtros['fecha_inicio'])) {
                $consulta->where('produccion.fecha', '>=', $filtros['fecha_inicio']);
            }
            if (! empty($filtros['fecha_fin'])) {
                $consulta->where('produccion.fecha', '<=', $filtros['fecha_fin']);
            }
            if (! empty($filtros['turno_id'])) {
                $consulta->where('produccion.turno_id', $filtros['turno_id']);
            }
            $union = $union ? $union->unionAll($consulta) : $consulta;
        }

        // SQL pagina los lotes de las tres tablas antes de hidratar modelos/relaciones.
        $pagina = DB::query()->fromSub($union, 'lotes')
            ->orderByDesc('fecha')->orderByDesc('id')->orderBy('familia')
            ->paginate(15)->appends(array_filter($filtros, fn ($valor) => $valor !== null && $valor !== ''));
        $cargados = collect();
        $pivotes = new Collection;
        foreach ($pagina->getCollection()->groupBy('familia') as $familia => $filas) {
            $relaciones = ['producto', 'produccion.turno', 'produccion.usuario', 'usuario', 'empleados'];
            if ($familia === 'pan') {
                $relaciones[] = 'unidadMedida';
            }
            $detalles = $modelos[$familia]::with($relaciones)->whereKey($filas->pluck('id'))->get();
            foreach ($detalles as $detalle) {
                $cargados->put($familia.':'.$detalle->id, $detalle);
                foreach ($detalle->empleados as $empleado) {
                    $pivotes->push($empleado->pivot);
                }
            }
        }
        $pivotes->load('rolProduccion');
        $pagina->setCollection($pagina->getCollection()->map(fn ($fila) => $cargados->get($fila->familia.':'.$fila->id))->filter()->values());

        return $pagina;
    }
}
