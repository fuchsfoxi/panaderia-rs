<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarHistorialProduccionRequest;
use App\Models\Turno;
use App\Queries\ConsultarHistorialProduccion;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class HistorialController extends Controller
{
    public function index(ConsultarHistorialProduccionRequest $request, ConsultarHistorialProduccion $consultar): View
    {
        $turnos = Turno::orderBy('nombre_turnos')->get();
        $filtros = $request->filtros();
        $erroresFiltros = $request->erroresFiltros();
        $detalles = new LengthAwarePaginator([], 0, 15, 1, ['path' => route('history.index')]);

        if ($request->consultaValida()) {
            $detalles = $consultar->ejecutar($request->validated());
        }

        return view('history.index', compact('turnos', 'filtros', 'erroresFiltros', 'detalles'));
    }
}
