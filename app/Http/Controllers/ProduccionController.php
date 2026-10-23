<?php

namespace App\Http\Controllers;

use App\Actions\Produccion\RegistrarProduccionBocadito;
use App\Actions\Produccion\RegistrarProduccionPan;
use App\Actions\Produccion\RegistrarProduccionTorta;
use App\Http\Requests\ConsultarHistorialProduccionRequest;
use App\Http\Requests\ConsultarProduccionRequest;
use App\Http\Requests\RegistrarProduccionBocaditoRequest;
use App\Http\Requests\RegistrarProduccionPanRequest;
use App\Http\Requests\RegistrarProduccionTortaRequest;
use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\GrupoProduccion;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\RolProduccion;
use App\Models\Turno;
use App\Queries\ConsultarHistorialProduccion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\MessageBag;
use Illuminate\View\View;
use League\Flysystem\UnableToWriteFile;

class ProduccionController extends Controller
{
    public function index(ConsultarProduccionRequest $request, ConsultarHistorialProduccion $consultar): View
    {
        $categorias = Categoria::whereIn('nombre_categorias', ['Pan', 'Torta', 'Bocadito'])->get()->keyBy('nombre_categorias');
        $productosPorCategoria = Producto::with('unidadMedida')->whereIn('categoria_id', $categorias->modelKeys())
            ->where('activo', true)->orderBy('nombre_p')->get()->groupBy('categoria_id');
        $productosPan = $productosPorCategoria->get($categorias->get('Pan')?->id, collect());
        $productosTorta = $productosPorCategoria->get($categorias->get('Torta')?->id, collect());
        $productosBocadito = $productosPorCategoria->get($categorias->get('Bocadito')?->id, collect());

        $turnos = Turno::orderBy('nombre_turnos')->get();
        $empleados = Empleado::with('rolProduccion')->disponiblesProduccion()->orderBy('nombre_empleados')->orderBy('id')->get();
        $rolesProduccion = RolProduccion::orderBy('nombre_roles_produccion')->get();
        // Son propuestas de composición, nunca restricciones de pertenencia.
        $gruposDisponibles = GrupoProduccion::with(GrupoProduccion::RELACIONES_COMPOSICION)
            ->where('activo', true)->orderBy('nombre')->orderBy('id')->get()
            ->filter(fn ($grupo) => $grupo->problemasComposicion() === [])->values();
        $categoriasPorEmpleado = [];
        foreach ($gruposDisponibles as $grupo) {
            foreach ($grupo->integrantes->pluck('id')->push($grupo->maestro_id) as $empleadoId) {
                $categoriasPorEmpleado[$empleadoId][] = strtolower($grupo->categoria->nombre_categorias);
            }
        }

        $filtros = $request->filtros();
        $categoriaSeleccionada = $filtros['categoria'];
        $nombreCategoria = ucfirst($categoriaSeleccionada);
        $categoria = $categorias->get($nombreCategoria);
        $fechaSeleccionada = $filtros['fecha'];
        $turnoSeleccionadoId = $filtros['turno_id'];
        $turnoMananaId = $turnos->firstWhere('nombre_turnos', 'Mañana')?->id;
        if ($categoriaSeleccionada !== 'pan' && ! $turnoSeleccionadoId) {
            $turnoSeleccionadoId = $turnoMananaId;
        }
        $turnoSeleccionado = $request->consultaValida() && $turnoSeleccionadoId
            ? $turnos->firstWhere('id', $turnoSeleccionadoId) : null;
        $errorConsulta = $request->errorConsulta();
        $cabecerasDuplicadas = false;

        if ($turnoSeleccionado && $categoria) {
            $producciones = Produccion::where('fecha', $fechaSeleccionada)
                ->where('categoria_id', $categoria->id)
                ->where('turno_id', $turnoSeleccionado->id)->limit(2)->get();
            $cabecerasDuplicadas = $producciones->count() > 1;
        }

        // Contexto inicial explícito; las consultas posteriores no modifican el formulario.
        $filtrosRegistros = ['fecha_inicio' => $fechaSeleccionada, 'fecha_fin' => $fechaSeleccionada,
            'turno_id' => (string) $turnoSeleccionadoId, 'categoria' => $categoriaSeleccionada];
        $erroresFiltros = new MessageBag;
        $detalles = new LengthAwarePaginator([], 0, 15, 1);
        if ($request->consultaValida() && ! $cabecerasDuplicadas) {
            $detalles = $consultar->ejecutar($filtrosRegistros)->withPath(route('produccion.registros'));
        }
        $detallesRegistrados = new Collection($detalles->items());

        return view('produccion.index', compact(
            'productosPan', 'productosTorta', 'productosBocadito', 'categoriaSeleccionada', 'nombreCategoria', 'turnos', 'empleados', 'rolesProduccion',
            'fechaSeleccionada', 'turnoSeleccionadoId', 'turnoSeleccionado',
            'detallesRegistrados', 'cabecerasDuplicadas', 'errorConsulta',
            'gruposDisponibles', 'categoriasPorEmpleado', 'turnoMananaId',
            'detalles', 'filtrosRegistros', 'erroresFiltros',
        ));
    }

    public function registros(ConsultarHistorialProduccionRequest $request, ConsultarHistorialProduccion $consultar): View
    {
        $erroresFiltros = $request->erroresFiltros();
        $detalles = $request->consultaValida()
            ? $consultar->ejecutar($request->validated())->withPath(route('produccion.registros'))
            : new LengthAwarePaginator([], 0, 15, 1);

        return view('produccion.registros', compact('detalles', 'erroresFiltros'));
    }

    public function store(RegistrarProduccionPanRequest $request, RegistrarProduccionPan $registrar): RedirectResponse
    {
        return $this->registrar($request, $registrar, 'Pan');
    }

    public function storeTorta(RegistrarProduccionTortaRequest $request, RegistrarProduccionTorta $registrar): RedirectResponse
    {
        return $this->registrar($request, $registrar, 'Torta');
    }

    public function storeBocadito(RegistrarProduccionBocaditoRequest $request, RegistrarProduccionBocadito $registrar): RedirectResponse
    {
        return $this->registrar($request, $registrar, 'Bocadito');
    }

    private function registrar(FormRequest $request, RegistrarProduccionPan|RegistrarProduccionTorta|RegistrarProduccionBocadito $registrar, string $familia): RedirectResponse
    {
        try {
            $produccion = $registrar->ejecutar($request->validated(), (int) $request->user()->getAuthIdentifier());
        } catch (QueryException|UnableToWriteFile $exception) {
            report($exception);

            return back()->withErrors([
                'produccion' => "No se pudo guardar la producción de $familia. No se registró ningún dato; vuelva a intentarlo.",
            ])->withInput();
        }

        $contexto = ['fecha' => $produccion->fecha->format('Y-m-d'), 'turno_id' => $produccion->turno_id];
        if ($familia !== 'Pan') {
            $contexto['categoria'] = strtolower($familia);
        }

        return redirect()->route('produccion.index', $contexto)
            ->with('success', $familia === 'Torta' ? 'Torta guardada' : "$familia guardado");
    }
}
