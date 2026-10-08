<?php

use App\Actions\Produccion\RegistrarProduccionPan;
use App\Models\Categoria;
use App\Models\DetallePan;
use App\Models\DetallePanEmpleado;
use App\Models\Empleado;
use App\Models\Produccion;
use App\Models\RolProduccion;
use App\Models\Turno;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para probar la Action en MariaDB aislada.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    Categoria::create(['nombre_categorias' => 'Familia previa ficticia']);
    RolProduccion::create(['nombre_roles_produccion' => 'Rol previo ficticio']);
    Turno::create(['nombre_turnos' => 'Turno previo ficticio']);
    $this->fixtures = IsolatedMariaDb::fixtures();
    $this->datos = [
        'categoria' => 'pan', 'fecha' => '2026-10-05', 'turno_id' => $this->fixtures['turno']->id,
        'detalles' => [[
            'producto_id' => $this->fixtures['productos']['Pan']->id,
            'coches' => 1, 'latas_adicionales' => 5, 'observacion' => 'Lote ficticio',
            'participantes' => [
                ['empleado_id' => $this->fixtures['empleados'][0]->id, 'rol_produccion_id' => $this->fixtures['roles']['Maestro']->id],
                ['empleado_id' => $this->fixtures['empleados'][1]->id, 'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id],
            ],
        ]],
    ];
    $this->registrar = app(RegistrarProduccionPan::class);
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

test('la Action registra sin Request y deriva turno unidad y snapshot del contexto real', function (?int $factor) {
    $producto = $this->fixtures['productos']['Pan'];
    $producto->update(['panes_por_lata' => $factor]);
    $produccion = $this->registrar->ejecutar($this->datos, $this->fixtures['usuario']->id);
    $detalle = $produccion->detallesPan()->sole();

    expect($produccion)->toBeInstanceOf(Produccion::class)
        ->and($produccion->fecha->format('Y-m-d'))->toBe('2026-10-05')
        ->and($produccion->turno_id)->toBe($this->fixtures['turno']->id)
        ->and($produccion->registrado_por_usuario_id)->toBe($this->fixtures['usuario']->id)
        ->and($detalle->cantidad)->toBe(23)
        ->and($detalle->turno_id)->toBe($produccion->turno_id)
        ->and($detalle->unidad_medida_id)->toBe($producto->unidad_medida_id)
        ->and($detalle->panes_por_lata_usado)->toBe($factor)
        ->and($detalle->empleados)->toHaveCount(2)
        ->and($detalle->empleados->first()->pivot)->toBeInstanceOf(DetallePanEmpleado::class);
})->with([null, 12]);

test('la Action rechaza reglas de negocio independientemente del transporte HTTP', function (string $caso, string $campo) {
    $participantes = &$this->datos['detalles'][0]['participantes'];
    switch ($caso) {
        case 'cero Maestros':
            $participantes[0]['rol_produccion_id'] = $this->fixtures['roles']['Ayudante']->id;
            break;
        case 'dos Maestros':
            $participantes[1]['rol_produccion_id'] = $this->fixtures['roles']['Maestro']->id;
            break;
        case 'sin Ayudantes':
            $participantes = [$participantes[0]];
            break;
        case 'duplicado':
            $participantes[1]['empleado_id'] = $participantes[0]['empleado_id'];
            break;
        case 'otro rol':
            $participantes[1]['rol_produccion_id'] = $this->fixtures['roles']['Practicante']->id;
            break;
        case 'inactivo':
            $this->fixtures['productos']['Pan']->update(['activo' => false]);
            break;
        case 'otra categoría':
            $this->datos['detalles'][0]['producto_id'] = $this->fixtures['productos']['Torta']->id;
            break;
    }
    try {
        $this->registrar->ejecutar($this->datos, $this->fixtures['usuario']->id);
        $this->fail('La Action debía rechazar la regla de negocio.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($campo);
    }
    expect(Produccion::count())->toBe(0)->and(DetallePan::count())->toBe(0)
        ->and(DB::table('detalle_pan_empleado')->count())->toBe(0);
})->with([
    ['cero Maestros', 'detalles.0.participantes'],
    ['dos Maestros', 'detalles.0.participantes'],
    ['sin Ayudantes', 'detalles.0.participantes'],
    ['duplicado', 'detalles.0.participantes'],
    ['otro rol', 'detalles.0.participantes.1.rol_produccion_id'],
    ['inactivo', 'detalles.0.producto_id'],
    ['otra categoría', 'detalles.0.producto_id'],
]);

test('la Action admite más de dos Ayudantes sin inventar un máximo de equipo', function () {
    foreach (range(1, 4) as $numero) {
        $empleado = Empleado::create([
            'nombre_empleados' => 'Ayudante adicional ficticio '.$numero, 'cargo_id' => $this->fixtures['cargo']->id,
        ]);
        $this->datos['detalles'][0]['participantes'][] = [
            'empleado_id' => $empleado->id, 'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id,
        ];
    }
    $produccion = $this->registrar->ejecutar($this->datos, $this->fixtures['usuario']->id);
    $empleados = $produccion->detallesPan()->sole()->empleados;
    expect($empleados)->toHaveCount(6)
        ->and($empleados->where('pivot.rol_produccion_id', $this->fixtures['roles']['Maestro']->id))->toHaveCount(1)
        ->and($empleados->where('pivot.rol_produccion_id', $this->fixtures['roles']['Ayudante']->id))->toHaveCount(5);
});

test('la Action reutiliza cabecera conserva autor y crea lotes con snapshots históricos independientes', function () {
    $producto = $this->fixtures['productos']['Pan'];
    $producto->update(['panes_por_lata' => 12]);
    $primera = $this->registrar->ejecutar($this->datos, $this->fixtures['usuario']->id);
    $producto->update(['panes_por_lata' => 14]);
    $otroUsuario = Usuario::create([
        'username' => 'registrador.action.ficticio', 'password_hash' => $this->fixtures['usuario']->password_hash,
        'empleado_id' => $this->fixtures['empleados'][2]->id, 'rol_id' => $this->fixtures['rol']->id,
    ]);
    $segunda = $this->registrar->ejecutar($this->datos, $otroUsuario->id);
    $detalles = $segunda->detallesPan()->orderBy('id')->get();
    expect($segunda->id)->toBe($primera->id)
        ->and(Produccion::count())->toBe(1)
        ->and($segunda->registrado_por_usuario_id)->toBe($this->fixtures['usuario']->id)
        ->and($detalles)->toHaveCount(2)
        ->and($detalles->pluck('cantidad')->all())->toBe([23, 23])
        ->and($detalles->pluck('panes_por_lata_usado')->all())->toBe([12, 14])
        ->and(DB::table('detalle_pan_empleado')->count())->toBe(4);
});

test('la Action rechaza cabeceras duplicadas conservando los lotes anteriores', function () {
    $primera = $this->registrar->ejecutar($this->datos, $this->fixtures['usuario']->id);
    Produccion::create($primera->only(['fecha', 'categoria_id', 'turno_id', 'registrado_por_usuario_id']));
    expect(fn () => $this->registrar->ejecutar($this->datos, $this->fixtures['usuario']->id))
        ->toThrow(ValidationException::class);
    expect(Produccion::count())->toBe(2)->and(DetallePan::count())->toBe(1)
        ->and(DB::table('detalle_pan_empleado')->count())->toBe(2);
});

test('la Action revierte errores SQL y de programación sin ocultarlos y mantiene eventos del Pivot', function (bool $sesionExistente, bool $falloSql) {
    if ($sesionExistente) {
        $this->registrar->ejecutar($this->datos, $this->fixtures['usuario']->id);
    }
    $cabecerasAntes = Produccion::get()->toArray();
    $detallesAntes = DetallePan::get()->toArray();
    $pivotsAntes = DB::table('detalle_pan_empleado')->orderBy('detalle_pan_id')->orderBy('empleado_id')->get()->toArray();
    $nivelInicial = DB::transactionLevel();
    $dispatcher = Model::getEventDispatcher();
    Model::setEventDispatcher(clone $dispatcher);
    $intentos = 0;
    $rolInexistente = RolProduccion::max('id') + 100;
    try {
        DetallePanEmpleado::creating(function ($pivot) use (&$intentos, $falloSql, $rolInexistente) {
            if (++$intentos === 2) {
                if ($falloSql) {
                    $pivot->rol_produccion_id = $rolInexistente;
                } else {
                    throw new RuntimeException('Error de programación ficticio.');
                }
            }
        });
        expect(fn () => $this->registrar->ejecutar($this->datos, $this->fixtures['usuario']->id))
            ->toThrow($falloSql ? QueryException::class : RuntimeException::class);
        expect($intentos)->toBe(2)
            ->and(DB::transactionLevel())->toBe($nivelInicial)
            ->and(Produccion::get()->toArray())->toBe($cabecerasAntes)
            ->and(DetallePan::get()->toArray())->toBe($detallesAntes)
            ->and(DB::table('detalle_pan_empleado')->orderBy('detalle_pan_id')->orderBy('empleado_id')->get()->toArray())->toEqual($pivotsAntes);
    } finally {
        Model::setEventDispatcher($dispatcher);
    }
})->with([
    'SQL en sesión nueva' => [false, true],
    'SQL en sesión existente' => [true, true],
    'programación en sesión nueva' => [false, false],
    'programación en sesión existente' => [true, false],
]);
