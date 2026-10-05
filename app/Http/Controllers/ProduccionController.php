<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\RolProduccion;
use App\Models\Turno;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProduccionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $categoriaPan = Categoria::where('nombre_categorias', 'Pan')->firstOrFail();
        $productosPan = Producto::where('categoria_id', $categoriaPan->id)
            ->where('activo', true)->orderBy('nombre_p')->get();
        $turnos = Turno::orderBy('nombre_turnos')->get();
        $empleados = Empleado::orderBy('nombre_empleados')->get();
        $rolesProduccion = RolProduccion::orderBy('nombre_roles_produccion')->get();

        // old() tiene prioridad al recuperar un POST rechazado.
        $fechaSeleccionada = $request->old('fecha', $request->query('fecha', now()->toDateString()));
        $turnoSeleccionadoId = $request->old('turno_id', $request->query('turno_id'));
        $consulta = Validator::make([
            'fecha' => $fechaSeleccionada, 'turno_id' => $turnoSeleccionadoId,
        ], [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'turno_id' => ['nullable', 'integer', 'exists:turnos,id'],
        ]);
        $turnoSeleccionado = $consulta->passes() && $turnoSeleccionadoId
            ? $turnos->firstWhere('id', $turnoSeleccionadoId) : null;
        $errorConsulta = $consulta->fails() && ! $request->session()->hasOldInput()
            ? 'La fecha o el turno de consulta no son válidos.' : null;
        // Parámetros manipulados en GET no deben llegar como arrays a los atributos HTML.
        $fechaSeleccionada = is_scalar($fechaSeleccionada) ? (string) $fechaSeleccionada : '';
        $turnoSeleccionadoId = is_scalar($turnoSeleccionadoId) ? (string) $turnoSeleccionadoId : null;
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

    public function store(Request $request)
    {
        $datos = $request->validate([
            'categoria' => ['required', Rule::in(['pan'])],
            'fecha' => ['required', 'date'],
            'turno_id' => ['required', 'integer', 'exists:turnos,id'],
            'detalles' => ['required', 'array', 'size:1', 'required_array_keys:0'],
            'detalles.*' => ['required', 'array'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            // cantidad es INT firmado: evitar desbordamientos al calcular y persistir.
            'detalles.*.coches' => ['required', 'integer', 'min:0', 'max:119304647'],
            'detalles.*.latas_adicionales' => ['required', 'integer', 'between:0,17'],
            'detalles.*.observacion' => ['nullable', 'string'],
            'detalles.*.participantes' => ['required', 'array', 'min:1'],
            'detalles.*.participantes.*' => ['required', 'array'],
            'detalles.*.participantes.*.empleado_id' => ['required', 'integer', 'exists:empleados,id'],
            'detalles.*.participantes.*.rol_produccion_id' => ['required', 'integer', 'exists:roles_produccion,id'],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'array' => 'El campo :attribute debe ser una lista válida.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'string' => 'El campo :attribute debe ser texto.',
            'date' => 'La fecha debe ser una fecha válida.',
            'min.numeric' => 'El campo :attribute no puede ser negativo.',
            'min.array' => 'Debe indicar al menos un elemento en :attribute.',
            'max.numeric' => 'El campo :attribute excede la cantidad máxima permitida.',
            'detalles.size' => 'Registre exactamente un producto de Pan por envío.',
            'detalles.required_array_keys' => 'El producto de Pan debe enviarse en detalles[0].',
            'detalles.*.latas_adicionales.between' => 'Las latas deben estar entre 0 y 17.',
            'categoria.in' => 'Solo se puede registrar Pan. Torta y Bocadito todavía no están implementados.',
            'turno_id.exists' => 'El turno seleccionado no existe.',
            'detalles.*.producto_id.exists' => 'El producto seleccionado no existe.',
            'detalles.*.participantes.*.empleado_id.exists' => 'El empleado seleccionado no existe.',
            'detalles.*.participantes.*.rol_produccion_id.exists' => 'El rol de producción seleccionado no existe.',
        ], [
            'categoria' => 'categoría',
            'fecha' => 'fecha',
            'turno_id' => 'turno',
            'detalles' => 'detalles de Pan',
            'detalles.*' => 'detalle de Pan',
            'detalles.*.producto_id' => 'producto',
            'detalles.*.coches' => 'coches',
            'detalles.*.latas_adicionales' => 'latas',
            'detalles.*.observacion' => 'observación',
            'detalles.*.participantes' => 'participantes',
            'detalles.*.participantes.*' => 'participante',
            'detalles.*.participantes.*.empleado_id' => 'empleado',
            'detalles.*.participantes.*.rol_produccion_id' => 'rol de producción',
        ]);

        $fecha = Carbon::parse($datos['fecha'])->toDateString();
        try {
            DB::transaction(function () use ($datos, $request, $fecha) {
                // Serializa los escritores de este flujo incluso sin UNIQUE SQL.
                $categoriaPan = Categoria::where('nombre_categorias', 'Pan')->lockForUpdate()->first();
                $roles = RolProduccion::whereIn('nombre_roles_produccion', ['Maestro', 'Ayudante'])
                    ->get()->keyBy('id');
                // Bloquear los productos mantiene estable el factor durante el snapshot.
                $detalle = $datos['detalles'][0];
                $producto = Producto::with('unidadMedida')->where('id', $detalle['producto_id'])
                    ->lockForUpdate()->first();
                $errores = [];
                if (! $categoriaPan) {
                    $errores['categoria'] = 'La categoría Pan no está disponible en el catálogo.';
                }

                $campo = 'detalles.0';
                if (! $producto || ! $producto->activo || $producto->categoria_id !== $categoriaPan?->id) {
                    $errores["$campo.producto_id"] = 'Debe seleccionar un producto activo de la categoría Pan.';
                } elseif (! $producto->unidadMedida) {
                    $errores["$campo.producto_id"] = 'El producto no tiene una unidad de medida válida. Revise su catálogo.';
                }

                $totalLatas = (int) $detalle['coches'] * 18 + (int) $detalle['latas_adicionales'];
                if ($totalLatas <= 0 || $totalLatas > 2147483647) {
                    $errores["$campo.coches"] = 'El total de latas debe ser mayor que cero y no exceder 2147483647.';
                }

                $empleados = [];
                $maestros = 0;
                $ayudantes = 0;
                foreach ($detalle['participantes'] as $posicion => $participante) {
                    $empleadoId = (int) $participante['empleado_id'];
                    if (isset($empleados[$empleadoId])) {
                        $errores["$campo.participantes"][] = 'Un empleado no puede repetirse dentro del mismo detalle.';
                    }
                    $empleados[$empleadoId] = true;
                    $rol = $roles->get($participante['rol_produccion_id']);
                    if (! $rol) {
                        $errores["$campo.participantes.$posicion.rol_produccion_id"] = 'Para Pan solo se permiten los roles Maestro y Ayudante.';
                    } elseif ($rol->nombre_roles_produccion === 'Maestro') {
                        $maestros++;
                    } else {
                        $ayudantes++;
                    }
                }
                if ($maestros !== 1) {
                    $errores["$campo.participantes"][] = 'Cada detalle de Pan debe tener exactamente un Maestro.';
                }
                if ($ayudantes < 1) {
                    $errores["$campo.participantes"][] = 'Cada detalle de Pan debe tener al menos un Ayudante.';
                }

                // Validar el único detalle antes de la primera escritura.
                if ($errores) {
                    throw ValidationException::withMessages($errores);
                }

                $producciones = Produccion::where('fecha', $fecha)
                    ->where('categoria_id', $categoriaPan->id)
                    ->where('turno_id', $datos['turno_id'])
                    ->lockForUpdate()->limit(2)->get();
                if ($producciones->count() > 1) {
                    throw ValidationException::withMessages([
                        'produccion' => 'Existen varias sesiones de Pan para esta fecha y turno. Revise las cabeceras duplicadas antes de registrar otro producto.',
                    ]);
                }
                $produccion = $producciones->first() ?? Produccion::create([
                    'fecha' => $fecha,
                    'registrado_por_usuario_id' => $request->user()->getAuthIdentifier(),
                    'categoria_id' => $categoriaPan->id,
                    'turno_id' => $datos['turno_id'],
                ]);

                // Cada petición crea un detalle nuevo, aunque el producto se repita.
                $detallePan = $produccion->detallesPan()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $totalLatas,
                    'unidad_medida_id' => $producto->unidad_medida_id,
                    'turno_id' => $produccion->turno_id,
                    'panes_por_lata_usado' => $producto->panes_por_lata,
                    'observacion' => $detalle['observacion'] ?? null,
                ]);
                foreach ($detalle['participantes'] as $participante) {
                    $detallePan->empleados()->attach($participante['empleado_id'], [
                        'rol_produccion_id' => $participante['rol_produccion_id'],
                    ]);
                }
            });
        } catch (QueryException $exception) {
            report($exception);

            return back()->withErrors([
                'produccion' => 'No se pudo guardar la producción de Pan. No se registró ningún dato; vuelva a intentarlo.',
            ])->withInput();
        }

        return redirect()->route('produccion.index', ['fecha' => $fecha, 'turno_id' => $datos['turno_id']])
            ->with('success', 'Producto de Pan registrado correctamente.');
    }
    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
