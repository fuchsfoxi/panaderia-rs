<?php

namespace App\Http\Controllers;

use App\Actions\Personal\AdministrarGrupos;
use App\Http\Requests\CambiarEstadoGrupoRequest;
use App\Http\Requests\ConsultarGruposRequest;
use App\Http\Requests\GestionarGrupoRequest;
use App\Http\Requests\GuardarGrupoRequest;
use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\GrupoProduccion;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GrupoController extends Controller
{
    public function index(ConsultarGruposRequest $request): View
    {
        $categoria = $request->categoria();
        $erroresFiltros = $request->erroresFiltros();
        $categorias = $this->categorias();
        $consulta = GrupoProduccion::with(GrupoProduccion::RELACIONES_COMPOSICION)->orderBy('nombre')->orderBy('id');
        if (! $request->consultaValida()) {
            $consulta->whereRaw('1 = 0');
        } elseif ($categoria !== '') {
            $consulta->whereHas('categoria', fn ($query) => $query->where('nombre_categorias', $categoria));
        }
        $pagina = $request->consultaValida() ? (int) ($request->query('page') ?: 1) : 1;
        $grupos = $consulta->paginate(15, ['*'], 'page', $pagina)->appends($categoria !== '' ? ['categoria' => $categoria] : []);

        return view('grupos.index', compact('grupos', 'categorias', 'categoria', 'erroresFiltros'));
    }

    public function create(): View
    {
        return $this->formulario(new GrupoProduccion(['activo' => true]));
    }

    public function edit(GrupoProduccion $grupo): View
    {
        return $this->formulario($grupo->load(GrupoProduccion::RELACIONES_COMPOSICION));
    }

    public function store(GuardarGrupoRequest $request, AdministrarGrupos $grupos): RedirectResponse
    {
        return $this->guardar($request, fn () => $grupos->guardar($request->validated(), (int) $request->user()->id), 'Grupo creado correctamente.');
    }

    public function update(GuardarGrupoRequest $request, GrupoProduccion $grupo, AdministrarGrupos $grupos): RedirectResponse
    {
        return $this->guardar($request, fn () => $grupos->guardar($request->validated(), (int) $request->user()->id, $grupo->id), 'Grupo actualizado correctamente.');
    }

    public function estado(CambiarEstadoGrupoRequest $request, GrupoProduccion $grupo, AdministrarGrupos $grupos): RedirectResponse
    {
        return $this->guardar($request, fn () => $grupos->estado($grupo->id, $request->boolean('activo'), (int) $request->user()->id),
            $request->boolean('activo') ? 'Grupo activado correctamente.' : 'Grupo desactivado correctamente.');
    }

    private function categorias()
    {
        return Categoria::whereIn('nombre_categorias', GrupoProduccion::CATEGORIAS)->orderBy('nombre_categorias')->get();
    }

    private function formulario(GrupoProduccion $grupo): View
    {
        $categorias = $this->categorias();
        $empleados = Empleado::with('rolProduccion')->where(function ($query) use ($grupo) {
            $query->where(fn ($aptos) => $aptos->where('activo', true)
                ->whereHas('rolProduccion', fn ($roles) => $roles->whereIn('nombre_roles_produccion', ['Maestro', 'Ayudante', 'Practicante'])));
            if ($grupo->exists) {
                // Mostrar asignaciones obsoletas permite identificarlas y retirarlas explícitamente.
                $query->orWhere('empleados.id', $grupo->maestro_id)->orWhereHas('gruposComoAyudante', fn ($grupos) => $grupos->whereKey($grupo->id));
            }
        })->orderBy('nombre_empleados')->orderBy('id')->get();
        $maestros = $empleados->filter(fn ($empleado) => $empleado->id === $grupo->maestro_id
            || ($empleado->activo && $empleado->rolProduccion?->nombre_roles_produccion === 'Maestro'));
        $ayudantes = $empleados->filter(fn ($empleado) => $grupo->integrantes->contains('id', $empleado->id)
            || ($empleado->activo && in_array($empleado->rolProduccion?->nombre_roles_produccion, ['Ayudante', 'Practicante'], true)));

        return view('grupos.form', compact('grupo', 'categorias', 'maestros', 'ayudantes'));
    }

    private function guardar(GestionarGrupoRequest $request, Closure $operacion, string $mensaje): RedirectResponse
    {
        try {
            $operacion();
        } catch (QueryException $exception) {
            report($exception);

            return back()->withErrors(['grupo' => 'No se pudo guardar el grupo. No se aplicó ningún cambio; vuelve a intentarlo.'])
                ->withInput($request->only('nombre', 'categoria_id', 'maestro_id', 'integrantes', 'activo'));
        }

        return redirect()->route('grupos.index')->with('success', $mensaje);
    }
}
