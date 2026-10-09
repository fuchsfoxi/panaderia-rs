<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarHistorialProduccionRequest;
use App\Models\DetallePan;
use App\Models\Produccion;
use App\Models\Turno;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class HistorialController extends Controller
{
    public function index(ConsultarHistorialProduccionRequest $request): View
    {
        $turnos = Turno::orderBy('nombre_turnos')->get();
        $filtros = $request->filtros();
        $erroresFiltros = $request->erroresFiltros();
        $detalles = new LengthAwarePaginator([], 0, 15, 1, ['path' => route('history.index')]);

        if ($request->consultaValida()) {
            $validados = $request->validated();
            $detalles = DetallePan::query()
                ->whereHas('produccion', function ($query) use ($validados) {
                    $query->whereHas('categoria', fn ($categoria) => $categoria->where('nombre_categorias', 'Pan'));

                    if (! empty($validados['fecha_inicio'])) {
                        $query->where('fecha', '>=', $validados['fecha_inicio']);
                    }
                    if (! empty($validados['fecha_fin'])) {
                        $query->where('fecha', '<=', $validados['fecha_fin']);
                    }
                    if (! empty($validados['turno_id'])) {
                        $query->where('turno_id', $validados['turno_id']);
                    }
                })
                ->with(['producto', 'produccion.turno', 'produccion.usuario', 'empleados'])
                ->orderByDesc(Produccion::select('fecha')->whereColumn('produccion.id', 'detalle_pan.produccion_id'))
                ->orderByDesc('detalle_pan.id')
                ->paginate(15)
                ->appends(array_filter($validados, fn ($valor) => $valor !== null && $valor !== ''));

            // Cargar la relación del Pivot existente en una sola consulta por página.
            $pivotes = new Collection($detalles->getCollection()->flatMap(
                fn ($detalle) => $detalle->empleados->map(fn ($empleado) => $empleado->pivot),
            )->all());
            $pivotes->load('rolProduccion');
        }

        return view('history.index', compact('turnos', 'filtros', 'erroresFiltros', 'detalles'));
    }
}
