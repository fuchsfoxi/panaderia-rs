<?php

namespace App\Http\Controllers;

use App\Consultas\LineasProduccion;
use App\Models\DetalleBocadito;
use App\Models\DetalleBocaditoEmpleado;
use App\Models\DetallePan;
use App\Models\DetallePanEmpleado;
use App\Models\DetalleTorta;
use App\Models\DetalleTortaEmpleado;
use App\Models\Empleado;
use App\Models\Producto;
use App\Models\Produccion;
use App\Models\RolProduccion;
use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProduccionController extends Controller
{
    /**
     * DRY: tabla pivote de empleados de cada categoria, con el nombre de su
     * columna FK hacia el detalle.
     *
     * MODIFICADO: antes cada rama de store() repetia su propio foreach con las
     * 3 claves hardcodeadas. Ahora se declara una vez.
     *
     * @var array<string, array{0: class-string, 1: string}>
     */
    private const PIVOTES_EMPLEADOS = [
        'pan' => [DetallePanEmpleado::class, 'detalle_pan_id'],
        'torta' => [DetalleTortaEmpleado::class, 'detalle_torta_id'],
        'bocadito' => [DetalleBocaditoEmpleado::class, 'detalle_bocadito_id'],
    ];

    /**
     * Formulario de produccion + listado de "Registrado recientemente".
     *
     * MODIFICADO: antes el bloque de tarjetas y los <select> del formulario
     * estaban completos con datos fijos en el HTML (3 tarjetas de ejemplo y
     * 5 empleados / 2 roles / 2 turnos inventados). Ahora todo sale de la base.
     */
    public function index()
    {
        return view('produccion.index', $this->datosVista());
    }

    /**
     * Todas las variables que necesita la vista, en un solo lugar.
     *
     * @return array<string, mixed>
     */
    private function datosVista(): array
    {
        return [
            // MODIFICADO: las 3 tarjetas fijas ("Pan Carioca / 3 coches",
            // "Torta de Chocolate / Circular", "Alfajorcitos / 150 unidades")
            // ahora son lineas reales de produccion, de la mas reciente a la
            // mas antigua, con los empleados que las produjeron (conEmpleados).
            'lineas' => LineasProduccion::obtener([
                'conEmpleados' => true,
                'limite' => 6,
            ]),

            // MODIFICADO: el <select id="producto"> estaba vacio salvo el
            // placeholder. Se llenan solo los productos activos.
            'productos' => Producto::where('activo', true)
                ->orderBy('nombre_p')
                ->get(),

            // MODIFICADO: estos 3 <select> tenian <option> escrito a mano
            // (turno 1/2, 5 empleados, Maestro/Ayudante). Ahora el value es el
            // id real de la base, que es la FK que necesita la tabla pivote.
            'turnos' => Turno::orderBy('id')->get(),
            'empleados' => Empleado::orderBy('nombre_empleados')->get(),
            'rolesProduccion' => RolProduccion::orderBy('id')->get(),
        ];
    }

    /**
     * Registra una produccion nueva.
     *
     * MODIFICADO: el metodo estaba vacio ("pendiente, se hace al final del
     * sprint"), asi que el formulario no guardaba nada. Ahora valida, crea la
     * produccion + su detalle segun la categoria del producto y los pivotes de
     * empleados, todo dentro de una transaccion.
     */
    public function store(Request $request)
    {
        // La categoria se saca del PRODUCTO elegido y no del input oculto
        // 'categoria' del formulario (ese lo setea el JS con los botones y
        // podria no coincidir con el producto). Asi el registro nunca queda
        // guardado en una tabla que no le corresponde. La BD guarda
        // 'Pan'/'Torta'/'Bocadito' y el JS usa minusculas, por eso strtolower.
        $producto = Producto::with('categoria')->find($request->input('producto_id'));
        $categoria = strtolower((string) $producto?->categoria?->nombre_categorias);

        $esPan = $categoria === 'pan';
        $esTorta = $categoria === 'torta';
        $esBocadito = $categoria === 'bocadito';

        // Las reglas de cantidad/turno/forma/foto dependen de la categoria,
        // asi que se arman dinamicamente: solo se exige lo del renglon que
        // corresponde y el resto queda en nullable (no se guardan porque las
        // columnas de las otras categorias no existen).
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],

            'turno_id' => [$esPan ? 'required' : 'nullable', 'integer', 'exists:turnos,id'],
            // pan se cuenta en coches, bocadito en unidades
            'cantidad_coches' => [$esPan ? 'required' : 'nullable', 'numeric', 'min:0'],
            'cantidad_unidades' => [$esBocadito ? 'required' : 'nullable', 'numeric', 'min:0'],
            'forma' => [$esTorta ? 'required' : 'nullable', 'string', 'max:100'],
            'foto' => [$esTorta ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // el select de empleados manda el id del <option>, no el nombre
            'empleados' => ['required', 'array', 'min:1'],
            'empleados.*.empleado_id' => ['required', 'integer', 'exists:empleados,id'],
            'empleados.*.rol_id' => ['required', 'integer', 'exists:roles_produccion,id'],
        ]);

        // Un producto sin categoria valida no se puede ubicar en ninguna tabla
        if (! $esPan && ! $esTorta && ! $esBocadito) {
            return back()
                ->withInput()
                ->withErrors(['producto_id' => 'El producto seleccionado no pertenece a una categoria valida.']);
        }

        DB::transaction(function () use ($request, $data, $producto, $categoria, $esPan, $esTorta, $esBocadito) {
            $produccion = Produccion::create([
                'fecha' => $data['fecha'],
                'observaciones' => $data['observaciones'] ?? null,
                // usuario de la sesion (la ruta esta con auth:web)
                'registrado_por_usuario_id' => $request->user()->id,
            ]);

            $empleados = $data['empleados'];

            // La foto se sube antes de armar el detalle porque el valor que se
            // guarda es su ruta final.
            $foto = null;
            if ($esTorta) {
                // Se mueve directo a public/images/tortas (no se usa el disco
                // 'public' de Laravel, que deja el archivo en
                // storage/app/public y necesita php artisan storage:link para
                // que sea visible). public/images es donde ya viven las
                // imagenes del proyecto y se sirve sin symlink.
                File::ensureDirectoryExists(public_path('images/tortas'));

                // Nombre aleatorio para no sobreescribir si dos personas suben
                // "torta.png" el mismo dia.
                $nombreFoto = Str::random(40).'.'.$request->file('foto')->extension();
                $request->file('foto')->move(public_path('images/tortas'), $nombreFoto);

                // Con "/" inicial porque las vistas la imprimen directo en
                // src="" sin asset(), y asi funciona desde cualquier ruta.
                $foto = '/images/tortas/'.$nombreFoto;
            }

            // DRY: el detalle va a una tabla distinta segun la categoria y cada
            // una tiene sus propias columnas, asi que se selecciona con match.
            // Lo que SI es igual para las 3 es la lista de empleados, que se
            // escribe una sola vez mas abajo.
            $detalle = match (true) {
                $esPan => DetallePan::create([
                    'produccion_id' => $produccion->id,
                    'producto_id' => $producto->id,
                    'unidad_medida_id' => $producto->unidad_medida_id,
                    'turno_id' => $data['turno_id'],
                    'cantidad' => $data['cantidad_coches'],
                ]),
                $esTorta => DetalleTorta::create([
                    'produccion_id' => $produccion->id,
                    'producto_id' => $producto->id,
                    'unidad_medida_id' => $producto->unidad_medida_id,
                    'forma' => $data['forma'],
                    // No hay columna 'cantidad': cada fila ES una torta.
                    'foto' => $foto,
                ]),
                $esBocadito => DetalleBocadito::create([
                    'produccion_id' => $produccion->id,
                    'producto_id' => $producto->id,
                    // sin unidad: el bocadito ya se cuenta en unidades
                    'cantidad' => $data['cantidad_unidades'],
                ]),
            };

            // DRY: antes cada rama repetia su propio foreach con el pivote de
            // esa categoria. Ahora se elige el pivote una vez y se recorre una
            // sola vez. El nombre de la FK sale del pivote, no esta fijo.
            [$modeloPivote, $columnaFk] = self::PIVOTES_EMPLEADOS[$categoria];

            foreach ($empleados as $emp) {
                $modeloPivote::create([
                    $columnaFk => $detalle->id,
                    'empleado_id' => $emp['empleado_id'],
                    'rol_produccion_id' => $emp['rol_id'],
                ]);
            }
        });

        return redirect()
            ->route('produccion.index')
            ->with('status', 'Produccion registrada correctamente.');
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
