<?php

namespace App\Http\Controllers;

use App\Models\DetallePan;
use App\Models\Produccion;
use App\Models\Turno;
use App\Models\UnidadMedida;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $fechaHoy = now()->startOfDay();
        $lotesPan = DetallePan::query()->whereHas('produccion.categoria',
            fn ($categoria) => $categoria->where('nombre_categorias', 'Pan'),
        );

        // Agregar por turno de cabecera, sin cargar los lotes del día en memoria.
        $resumenHoy = (clone $lotesPan)
            ->join('produccion', 'produccion.id', '=', 'detalle_pan.produccion_id')
            ->leftJoin('unidades_medida as unidad_historica', 'unidad_historica.id', '=', 'detalle_pan.unidad_medida_id')
            ->where('produccion.fecha', $fechaHoy->toDateString())
            ->select('produccion.turno_id')
            ->selectRaw('COUNT(*) as lotes, SUM(CASE WHEN unidad_historica.codigo = ? THEN detalle_pan.cantidad ELSE 0 END) as latas', [UnidadMedida::LATA])
            ->groupBy('produccion.turno_id')
            ->get();

        $totalLotesHoy = (int) $resumenHoy->sum('lotes');
        $totalLatasHoy = (int) $resumenHoy->sum('latas');
        $resumenPorId = $resumenHoy->keyBy('turno_id');
        $produccionPorTurno = Turno::orderBy('nombre_turnos')->get()->map(function (Turno $turno) use ($resumenPorId) {
            $resumen = $resumenPorId->get($turno->id);

            return [
                'nombre' => $turno->nombre_turnos,
                'lotes' => (int) ($resumen?->lotes ?? 0),
                'latas' => (int) ($resumen?->latas ?? 0),
            ];
        });

        // No atribuir un turno a cabeceras antiguas que aún no lo tienen.
        $sinTurno = $resumenHoy->first(fn ($resumen) => $resumen->turno_id === null);
        if ($sinTurno) {
            $produccionPorTurno->push([
                'nombre' => 'Sin turno registrado',
                'lotes' => (int) $sinTurno->lotes,
                'latas' => (int) $sinTurno->latas,
            ]);
        }

        $ultimasProducciones = (clone $lotesPan)
            ->with(['producto', 'unidadMedida', 'produccion.turno', 'produccion.usuario'])
            ->orderByDesc(Produccion::select('fecha')->whereColumn('produccion.id', 'detalle_pan.produccion_id'))
            ->orderByDesc('detalle_pan.id')
            ->limit(5)->get();

        return view('dashboard.index', compact(
            'fechaHoy', 'totalLotesHoy', 'totalLatasHoy', 'produccionPorTurno', 'ultimasProducciones',
        ));
    }
}
