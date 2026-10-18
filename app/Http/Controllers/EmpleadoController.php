<?php

namespace App\Http\Controllers;

use App\Actions\Personal\AdministrarEmpleados;
use App\Http\Requests\CambiarEstadoEmpleadoRequest;
use App\Http\Requests\GestionarEmpleadoRequest;
use App\Http\Requests\GuardarEmpleadoRequest;
use App\Models\Empleado;
use App\Models\RolProduccion;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmpleadoController extends Controller
{
    public function index(): View
    {
        $empleados = Empleado::with('rolProduccion')->orderBy('nombre_empleados')->orderBy('id')->paginate(15);

        return view('empleados.index', compact('empleados'));
    }

    public function create(): View
    {
        return $this->formulario(new Empleado(['activo' => true]));
    }

    public function edit(Empleado $empleado): View
    {
        return $this->formulario($empleado);
    }

    public function store(GuardarEmpleadoRequest $request, AdministrarEmpleados $empleados): RedirectResponse
    {
        return $this->guardar($request, fn () => $empleados->guardar($request->validated(), (int) $request->user()->id), 'Empleado creado correctamente.');
    }

    public function update(GuardarEmpleadoRequest $request, Empleado $empleado, AdministrarEmpleados $empleados): RedirectResponse
    {
        return $this->guardar($request, fn () => $empleados->guardar($request->validated(), (int) $request->user()->id, $empleado->id), 'Empleado actualizado correctamente.');
    }

    public function estado(CambiarEstadoEmpleadoRequest $request, Empleado $empleado, AdministrarEmpleados $empleados): RedirectResponse
    {
        return $this->guardar($request, fn () => $empleados->estado($empleado->id, $request->boolean('activo'), (int) $request->user()->id),
            $request->boolean('activo') ? 'Empleado activado correctamente.' : 'Empleado desactivado correctamente.');
    }

    private function formulario(Empleado $empleado): View
    {
        $roles = RolProduccion::whereIn('nombre_roles_produccion', RolProduccion::NOMBRES)->orderBy('nombre_roles_produccion')->get();

        return view('empleados.form', compact('empleado', 'roles'));
    }

    private function guardar(GestionarEmpleadoRequest $request, Closure $operacion, string $mensaje): RedirectResponse
    {
        try {
            $operacion();
        } catch (QueryException $exception) {
            report($exception);

            return back()->withErrors(['empleado' => 'No se pudo guardar el empleado. Vuelve a intentarlo.'])
                ->withInput($request->only('nombre_empleados', 'rol_produccion_id', 'activo'));
        }

        return redirect()->route('empleados.index')->with('success', $mensaje);
    }
}
