<?php

namespace App\Http\Controllers;

use App\Actions\Produccion\RegistrarProduccionPan;
use App\Http\Requests\ConsultarProduccionRequest;
use App\Http\Requests\RegistrarProduccionPanRequest;
use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\RolProduccion;
use App\Models\Turno;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProduccionController extends Controller
{
    public function index(ConsultarProduccionRequest $request): View
    {
        $categoriaPan = Categoria::where('nombre_categorias', 'Pan')->firstOrFail();
        $productosPan = Producto::where('categoria_id', $categoriaPan->id)
            ->where('activo', true)->orderBy('nombre_p')->get();
        $turnos = Turno::orderBy('nombre_turnos')->get();
        $empleados = Empleado::orderBy('nombre_empleados')->get();
        $rolesProduccion = RolProduccion::orderBy('nombre_roles_produccion')->get();

        $filtros = $request->filtros();
        $fechaSeleccionada = $filtros['fecha'];
        $turnoSeleccionadoId = $filtros['turno_id'];
        $turnoSeleccionado = $request->consultaValida() && $turnoSeleccionadoId
            ? $turnos->firstWhere('id', $turnoSeleccionadoId) : null;
        $errorConsulta = $request->errorConsulta();
        $cabecerasDuplicadas = false;
        $detallesRegistrados = collect();

        if ($turnoSeleccionado) {
            $producciones = Produccion::where('fecha', $fechaSeleccionada)
                ->where('categoria_id', $categoriaPan->id)
                ->where('turno_id', $turnoSeleccionado->id)->limit(2)->get();
            $cabecerasDuplicadas = $producciones->count() > 1;
            if ($producciones->count() === 1) {
                $detallesRegistrados = $producciones->first()->detallesPan()
                    ->with(['producto', 'empleados'])->orderBy('id')->get();
            }
        }

        return view('produccion.index', compact(
            'productosPan', 'turnos', 'empleados', 'rolesProduccion',
            'fechaSeleccionada', 'turnoSeleccionadoId', 'turnoSeleccionado',
            'detallesRegistrados', 'cabecerasDuplicadas', 'errorConsulta',
        ));
    }

    public function store(RegistrarProduccionPanRequest $request, RegistrarProduccionPan $registrar): RedirectResponse
    {
        try {
            $produccion = $registrar->ejecutar($request->validated(), (int) $request->user()->getAuthIdentifier());
        } catch (QueryException $exception) {
            report($exception);

            return back()->withErrors([
                'produccion' => 'No se pudo guardar la producción de Pan. No se registró ningún dato; vuelva a intentarlo.',
            ])->withInput();
        }

        return redirect()->route('produccion.index', [
            'fecha' => $produccion->fecha->format('Y-m-d'),
            'turno_id' => $produccion->turno_id,
        ])->with('success', 'Producto de Pan registrado correctamente.');
    }
}
