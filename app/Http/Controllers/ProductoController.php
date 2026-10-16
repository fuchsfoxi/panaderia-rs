<?php

namespace App\Http\Controllers;

use App\Actions\Productos\ActualizarProducto;
use App\Actions\Productos\CrearProducto;
use App\Http\Requests\ActualizarProductoRequest;
use App\Http\Requests\CambiarEstadoProductoRequest;
use App\Http\Requests\ConsultarProductosRequest;
use App\Http\Requests\CrearProductoRequest;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductoController extends Controller
{
    public function index(ConsultarProductosRequest $request): View
    {
        $filtros = $request->filtros();
        $consulta = Producto::with(['categoria', 'unidadMedida'])->orderBy('nombre_p')->orderBy('id');
        if (! $request->consultaValida()) {
            $consulta->whereRaw('1 = 0');
        } else {
            if ($filtros['q'] !== '') {
                // Tratar % y _ como texto de búsqueda, no como comodines enviados por el cliente.
                $consulta->whereRaw("nombre_p LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filtros['q']).'%']);
            }
            if ($filtros['categoria_id'] !== '') {
                $consulta->where('categoria_id', $filtros['categoria_id']);
            }
            if (in_array($filtros['estado'], ['activos', 'inactivos'], true)) {
                $consulta->where('activo', $filtros['estado'] === 'activos');
            }
        }
        $productos = $consulta->paginate(15, page: $request->pagina())->appends(array_filter($filtros, fn ($v) => $v !== ''));
        $categorias = Categoria::orderBy('nombre_categorias')->get();
        $erroresFiltros = $request->erroresFiltros();

        return view('productos.index', compact('productos', 'categorias', 'filtros', 'erroresFiltros'));
    }

    public function create(): View
    {
        return $this->formulario(new Producto);
    }

    public function edit(Producto $producto): View
    {
        return $this->formulario($producto);
    }

    public function store(CrearProductoRequest $request, CrearProducto $crear): RedirectResponse
    {
        try {
            $crear->ejecutar($request->validated());
        } catch (QueryException $exception) {
            return $this->falloGuardado($exception);
        }

        return redirect()->route('productos.index')->with('success', 'Producto creado correctamente.');
    }

    public function update(ActualizarProductoRequest $request, Producto $producto, ActualizarProducto $actualizar): RedirectResponse
    {
        try {
            $actualizar->ejecutar($producto->id, $request->validated());
        } catch (QueryException $exception) {
            return $this->falloGuardado($exception);
        }

        return redirect()->route('productos.edit', $producto)->with('success', 'Producto actualizado correctamente.');
    }

    public function estado(CambiarEstadoProductoRequest $request, Producto $producto, ActualizarProducto $actualizar): RedirectResponse
    {
        try {
            // Asignación explícita e idempotente: una repetición no invierte el estado.
            $producto = $actualizar->ejecutar($producto->id, ['activo' => $request->boolean('activo')]);
        } catch (QueryException $exception) {
            return $this->falloGuardado($exception);
        }

        return redirect()->route('productos.index', $request->query())
            ->with('success', $producto->activo ? 'Producto activado correctamente.' : 'Producto desactivado correctamente.');
    }

    private function formulario(Producto $producto): View
    {
        $categorias = Categoria::whereIn('nombre_categorias', ['Pan', 'Torta', 'Bocadito'])->orderBy('nombre_categorias')->get();
        $unidades = UnidadMedida::whereIn('codigo', [UnidadMedida::LATA, UnidadMedida::UNIDAD])->get()->keyBy('codigo');
        $tieneHistoria = $producto->exists && ($producto->detallesPan()->exists() || $producto->detallesTorta()->exists() || $producto->detallesBocadito()->exists());
        $valores = [];
        foreach (['nombre_p', 'categoria_id', 'unidad_medida_id', 'panes_por_lata'] as $campo) {
            $valor = old($campo, $producto->$campo);
            if ($tieneHistoria && in_array($campo, ['categoria_id', 'unidad_medida_id'], true)) {
                $valor = $producto->$campo;
            }
            $valores[$campo] = is_scalar($valor) ? (string) $valor : '';
        }

        return view('productos.form', compact('producto', 'categorias', 'unidades', 'tieneHistoria', 'valores'));
    }

    private function falloGuardado(QueryException $exception): RedirectResponse
    {
        report($exception);

        return back()->withErrors(['producto' => 'No se pudo guardar el producto. Vuelve a intentarlo.'])
            ->withInput(request()->only(['nombre_p', 'categoria_id', 'unidad_medida_id', 'panes_por_lata']));
    }
}
