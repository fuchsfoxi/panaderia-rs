<?php

use App\Actions\Produccion\RegistrarProduccionPan;
use App\Models\Categoria;
use App\Models\DetallePan;
use App\Models\DetallePanEmpleado;
use App\Models\Produccion;
use App\Models\RolProduccion;
use App\Models\Turno;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para probar el historial en MariaDB aislada.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    Categoria::create(['nombre_categorias' => 'Otra familia ficticia']);
    RolProduccion::create(['nombre_roles_produccion' => 'Otro rol ficticio']);
    Turno::create(['nombre_turnos' => 'Otro turno ficticio']);
    $this->fixtures = IsolatedMariaDb::fixtures();
    $this->noche = Turno::create(['nombre_turnos' => 'Noche']);
    $this->payload = [
        'categoria' => 'pan', 'fecha' => '2026-10-05', 'turno_id' => $this->fixtures['turno']->id,
        'detalles' => [[
            'producto_id' => $this->fixtures['productos']['Pan']->id,
            'coches' => 2, 'latas_adicionales' => 4, 'observacion' => 'Nota real del lote de prueba',
            'participantes' => [
                ['empleado_id' => $this->fixtures['empleados'][0]->id, 'rol_produccion_id' => $this->fixtures['roles']['Maestro']->id],
                ['empleado_id' => $this->fixtures['empleados'][1]->id, 'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id],
                ['empleado_id' => $this->fixtures['empleados'][2]->id, 'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id],
            ],
        ]],
    ];
    $this->withoutVite();
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

function registrarLoteHistorial(array $datos, array $fixtures): DetallePan
{
    $produccion = app(RegistrarProduccionPan::class)->ejecutar($datos, $fixtures['usuario']->id);

    return $produccion->detallesPan()->orderByDesc('id')->firstOrFail();
}

test('un invitado no accede al historial incluso con filtros inválidos', function () {
    $this->get(route('history.index', ['turno_id' => ['incorrecto']]))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('un autenticado ve el historial vacío y los catálogos reales sin IDs fijos', function () {
    expect($this->fixtures['turno']->id)->not->toBe(1)
        ->and($this->fixtures['productos']['Pan']->categoria_id)->not->toBe(1)
        ->and($this->fixtures['roles']['Maestro']->id)->not->toBe(1);
    $this->actingAs($this->fixtures['usuario'])->get(route('history.index'))->assertOk()
        ->assertSeeText('0 registros encontrados')
        ->assertSeeText('No se encontraron producciones para los filtros seleccionados.')
        ->assertSee('value="'.$this->fixtures['turno']->id.'"', false)
        ->assertSeeText('Mañana')->assertSeeText('Noche')
        ->assertSee('disabled>Torta · Próximamente', false)
        ->assertSee('disabled>Bocadito · Próximamente', false);
});

test('login dashboard producción registro e historial funcionan con datos persistidos', function () {
    $this->get(route('login'))->assertOk();
    $this->post('/login', [
        'username' => $this->fixtures['usuario']->username, 'password' => 'clave-ficticia-sprint4',
    ])->assertRedirect('dashboard');
    $this->assertAuthenticatedAs($this->fixtures['usuario']);
    $this->get(route('dashboard'))->assertOk();
    $this->get(route('produccion.index'))->assertOk();
    $this->post(route('produccion.store'), $this->payload)->assertRedirect(route('produccion.index', [
        'fecha' => $this->payload['fecha'], 'turno_id' => $this->payload['turno_id'],
    ]))->assertSessionHasNoErrors();

    $cantidad = 2 * DetallePan::LATAS_POR_COCHE + 4;
    foreach ([[], ['fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-05'], ['turno_id' => $this->payload['turno_id']]] as $filtros) {
        $response = $this->get(route('history.index', $filtros))->assertOk()
            ->assertSeeText('Pan ficticio')->assertSeeText('Turno: Mañana')
            ->assertSeeText('Cantidad: '.$cantidad.' latas en total')->assertSeeText('2 coches + 4 latas')
            ->assertSeeText('Nota real del lote de prueba')
            ->assertSeeText('Maestro: Empleado ficticio 1')
            ->assertSeeText('Ayudantes: Empleado ficticio 2, Empleado ficticio 3')
            ->assertSeeText('Registró la sesión: '.$this->fixtures['usuario']->username);
        $detalle = $response->viewData('detalles')->sole();
        expect($detalle->cantidad)->toBe($cantidad)
            ->and($detalle->empleados->first()->pivot)->toBeInstanceOf(DetallePanEmpleado::class)
            ->and($detalle->empleados->first()->pivot->relationLoaded('rolProduccion'))->toBeTrue();
    }
});

test('filtra fechas opcionales con límites inclusivos y ordena por fecha y detalle descendentes', function (array $filtros, array $fechas) {
    $idsPorFecha = [];
    // Inserción desordenada para que el orden por ID no sustituya al de fecha.
    foreach (['2026-10-05', '2026-10-07', '2026-10-04', '2026-10-06'] as $fecha) {
        $datos = array_replace($this->payload, ['fecha' => $fecha]);
        $idsPorFecha[$fecha] = registrarLoteHistorial($datos, $this->fixtures)->id;
    }
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('history.index', $filtros))->assertOk();
    expect($response->viewData('detalles')->pluck('id')->all())
        ->toBe(array_map(fn ($fecha) => $idsPorFecha[$fecha], $fechas));
})->with([
    'sin filtros' => [[], ['2026-10-07', '2026-10-06', '2026-10-05', '2026-10-04']],
    'desde' => [['fecha_inicio' => '2026-10-06'], ['2026-10-07', '2026-10-06']],
    'hasta' => [['fecha_fin' => '2026-10-05'], ['2026-10-05', '2026-10-04']],
    'rango' => [['fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-06'], ['2026-10-06', '2026-10-05']],
    'día exacto' => [['fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-05'], ['2026-10-05']],
    'vacíos' => [['fecha_inicio' => '', 'fecha_fin' => '', 'turno_id' => ''], ['2026-10-07', '2026-10-06', '2026-10-05', '2026-10-04']],
]);

test('consulta turno de cabecera aunque el detalle legacy tenga otro turno', function () {
    $dia = registrarLoteHistorial($this->payload, $this->fixtures);
    $dia->update(['turno_id' => $this->noche->id]);
    $noche = registrarLoteHistorial(array_replace($this->payload, ['turno_id' => $this->noche->id]), $this->fixtures);
    $this->actingAs($this->fixtures['usuario']);
    $response = $this->get(route('history.index', ['turno_id' => $this->fixtures['turno']->id]))
        ->assertOk()->assertSeeText('Turno: Mañana')->assertDontSeeText('Turno: Noche');
    expect($response->viewData('detalles')->pluck('id')->all())->toBe([$dia->id]);
    $response = $this->get(route('history.index', ['turno_id' => $this->noche->id]))->assertOk();
    expect($response->viewData('detalles')->pluck('id')->all())->toBe([$noche->id]);
});

test('solo consulta sesiones de Pan y conserva productos desactivados en su historial', function () {
    $pan = registrarLoteHistorial($this->payload, $this->fixtures);
    $this->fixtures['productos']['Pan']->update(['activo' => false]);
    $otra = Produccion::create([
        'fecha' => '2026-10-08', 'categoria_id' => $this->fixtures['productos']['Torta']->categoria_id,
        'turno_id' => $this->noche->id, 'registrado_por_usuario_id' => $this->fixtures['usuario']->id,
    ]);
    // Dato inconsistente deliberado: no debe atribuirse al historial de Pan.
    IsolatedMariaDb::detail(DetallePan::class, $otra, $this->fixtures);
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('history.index', ['categoria' => 'torta']))->assertOk();
    expect($response->viewData('detalles')->pluck('id')->all())->toBe([$pan->id]);
});

test('filtros sin coincidencias muestran el estado vacío sin datos ficticios', function () {
    registrarLoteHistorial($this->payload, $this->fixtures);
    $this->actingAs($this->fixtures['usuario'])->get(route('history.index', ['fecha_inicio' => '2026-10-09']))
        ->assertOk()->assertSeeText('0 registros encontrados')
        ->assertSeeText('No se encontraron producciones para los filtros seleccionados.')
        ->assertDontSeeText('Pan ficticio')->assertDontSeeText('23 registros encontrados');
});

test('filtros inválidos se muestran sin redirección ni consulta de detalles', function (array $filtros, string $campo) {
    registrarLoteHistorial($this->payload, $this->fixtures);
    $this->actingAs($this->fixtures['usuario']);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $response = $this->get(route('history.index', $filtros))->assertOk()
            ->assertSeeText('Revisa los filtros indicados para consultar el historial.')
            ->assertDontSeeText('Pan ficticio')->assertDontSeeText('0 registros encontrados');
        expect($response->viewData('erroresFiltros')->has($campo))->toBeTrue()
            ->and($response->viewData('detalles')->total())->toBe(0);
        $consultas = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        expect($consultas)->not->toContain('`detalle_pan`');
    } finally {
        DB::disableQueryLog();
    }
})->with([
    'desde imposible' => [['fecha_inicio' => '2026-02-30'], 'fecha_inicio'],
    'hasta texto' => [['fecha_fin' => 'ayer'], 'fecha_fin'],
    'rango invertido' => [['fecha_inicio' => '2026-10-06', 'fecha_fin' => '2026-10-05'], 'fecha_fin'],
    'turno inexistente' => [['turno_id' => 2147483647], 'turno_id'],
    'turno texto' => [['turno_id' => 'Mañana'], 'turno_id'],
    'desde array' => [['fecha_inicio' => ['incorrecto']], 'fecha_inicio'],
    'hasta array' => [['fecha_fin' => ['incorrecto']], 'fecha_fin'],
    'turno array' => [['turno_id' => ['incorrecto']], 'turno_id'],
]);

test('pagina lotes conserva los filtros combinados y usa detalle descendente en la misma fecha', function () {
    $ids = [];
    foreach (range(1, 16) as $numero) {
        $ids[] = registrarLoteHistorial($this->payload, $this->fixtures)->id;
    }
    registrarLoteHistorial(array_replace($this->payload, ['turno_id' => $this->noche->id]), $this->fixtures);
    registrarLoteHistorial(array_replace($this->payload, ['fecha' => '2026-10-07']), $this->fixtures);
    $filtros = ['fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-05', 'turno_id' => $this->fixtures['turno']->id];
    $this->actingAs($this->fixtures['usuario']);
    $response = $this->get(route('history.index', $filtros))->assertOk()->assertSeeText('16 registros encontrados');
    $pagina = $response->viewData('detalles');
    expect($pagina->count())->toBe(15)->and($pagina->pluck('id')->all())->toBe(array_slice(array_reverse($ids), 0, 15));
    parse_str(parse_url($pagina->nextPageUrl(), PHP_URL_QUERY), $consulta);
    expect($consulta)->toEqual(array_merge($filtros, ['page' => '2']));
    $segunda = $this->get($pagina->nextPageUrl())->assertOk()->assertSeeText('Página 2 de 2');
    expect($segunda->viewData('detalles')->pluck('id')->all())->toBe([$ids[0]]);
});

test('las consultas de relaciones y pivotes no crecen con los lotes de la página', function () {
    registrarLoteHistorial($this->payload, $this->fixtures);
    // El sidebar reutiliza la cuenta del guard; estabilizar esa lectura ajena al listado.
    $this->fixtures['usuario']->load('empleado');
    $this->actingAs($this->fixtures['usuario']);
    $medir = function (): int {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->get(route('history.index'))->assertOk();
            $consultas = collect(DB::getQueryLog());
            expect($consultas->filter(fn ($consulta) => str_contains($consulta['query'], 'from `roles_produccion`'))->count())->toBe(1);

            return $consultas->count();
        } finally {
            DB::disableQueryLog();
        }
    };
    $una = $medir();
    foreach (range(1, 14) as $numero) {
        registrarLoteHistorial($this->payload, $this->fixtures);
    }
    expect($medir())->toBe($una);
});

test('observación y nombres se escapan y participantes ausentes no se inventan', function () {
    $detalle = registrarLoteHistorial($this->payload, $this->fixtures);
    $detalle->update(['observacion' => '<script>alert("ejemplo")</script>']);
    $detalle->empleados()->detach();
    $this->actingAs($this->fixtures['usuario'])->get(route('history.index'))->assertOk()
        ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(', false)
        ->assertSeeText('Sin Maestro registrado')->assertSeeText('Sin Ayudantes registrados');
});
