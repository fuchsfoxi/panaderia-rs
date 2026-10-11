<?php

namespace App\Http\Controllers;

use App\Actions\Produccion\RegistrarProduccionBocadito;
use App\Actions\Produccion\RegistrarProduccionPan;
use App\Actions\Produccion\RegistrarProduccionTorta;
use App\Http\Requests\ConsultarProduccionRequest;
use App\Http\Requests\RegistrarProduccionBocaditoRequest;
use App\Http\Requests\RegistrarProduccionPanRequest;
use App\Http\Requests\RegistrarProduccionTortaRequest;
use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\RolProduccion;
use App\Models\Turno;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use League\Flysystem\UnableToWriteFile;

class ProduccionController extends Controller
{
    public function index(ConsultarProduccionRequest $request): View
    {
        $categorias = Categoria::whereIn('nombre_categorias', ['Pan', 'Torta', 'Bocadito'])->get()->keyBy('nombre_categorias');
        $productosPorCategoria = Producto::whereIn('categoria_id', $categorias->modelKeys())
            ->where('activo', true)->orderBy('nombre_p')->get()->groupBy('categoria_id');
        $productosPan = $productosPorCategoria->get($categorias->get('Pan')?->id, collect());
        $productosTorta = $productosPorCategoria->get($categorias->get('Torta')?->id, collect());
        $productosBocadito = $productosPorCategoria->get($categorias->get('Bocadito')?->id, collect());

        $turnos = Turno::orderBy('nombre_turnos')->get();
        $empleados = Empleado::orderBy('nombre_empleados')->get();
        $rolesProduccion = RolProduccion::orderBy('nombre_roles_produccion')->get();

        $filtros = $request->filtros();
        $categoriaSeleccionada = $filtros['categoria'];
        $nombreCategoria = ucfirst($categoriaSeleccionada);
        $categoria = $categorias->get($nombreCategoria);
        $fechaSeleccionada = $filtros['fecha'];
        $turnoSeleccionadoId = $filtros['turno_id'];
        $turnoSeleccionado = $request->consultaValida() && $turnoSeleccionadoId
            ? $turnos->firstWhere('id', $turnoSeleccionadoId) : null;
        $errorConsulta = $request->errorConsulta();
        $cabecerasDuplicadas = false;
        $detallesRegistrados = collect();

        if ($turnoSeleccionado && $categoria) {
            $producciones = Produccion::where('fecha', $fechaSeleccionada)
                ->where('categoria_id', $categoria->id)
                ->where('turno_id', $turnoSeleccionado->id)->limit(2)->get();
            $cabecerasDuplicadas = $producciones->count() > 1;
            if ($producciones->count() === 1) {
                $relacion = match ($categoriaSeleccionada) {
                    'pan' => 'detallesPan', 'torta' => 'detallesTorta', 'bocadito' => 'detallesBocadito',
                };
                $detallesRegistrados = $producciones->first()->$relacion()
                    ->with(['producto', 'empleados'])->orderBy('id')->get();
            }
        }

        return view('produccion.index', compact(
            'productosPan', 'productosTorta', 'productosBocadito', 'categoriaSeleccionada', 'nombreCategoria', 'turnos', 'empleados', 'rolesProduccion',
            'fechaSeleccionada', 'turnoSeleccionadoId', 'turnoSeleccionado',
            'detallesRegistrados', 'cabecerasDuplicadas', 'errorConsulta',
        ));
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
            ->with('success', "Producto de $familia registrado correctamente.");
    }
}
