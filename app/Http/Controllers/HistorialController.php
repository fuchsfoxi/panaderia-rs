<?php

namespace App\Http\Controllers;

use App\Actions\Produccion\EliminarRegistroProduccion;
use App\Http\Requests\ConsultarHistorialProduccionRequest;
use App\Http\Requests\EliminarRegistroProduccionRequest;
use App\Models\Turno;
use App\Queries\ConsultarHistorialProduccion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class HistorialController extends Controller
{
    public function destroy(EliminarRegistroProduccionRequest $request, string $familia, int $detalle, EliminarRegistroProduccion $eliminar): RedirectResponse
    {
        try {
            $eliminar->ejecutar($familia, $detalle);
        } catch (ModelNotFoundException) {
            return redirect()->route('history.index', $request->filtrosRetorno())
                ->with('error', 'El registro ya no está disponible. Actualiza el historial.');
        } catch (QueryException $exception) {
            report($exception);

            return redirect()->route('history.index', $request->filtrosRetorno())
                ->with('error', 'No se pudo eliminar el registro. Vuelve a intentarlo.');
        }

        return redirect()->route('history.index', $request->filtrosRetorno())
            ->with('success', 'Registro de producción eliminado correctamente.');
    }

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
