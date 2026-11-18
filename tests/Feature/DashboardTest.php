<?php

use App\Actions\Produccion\RegistrarProduccionPan;
use App\Models\Categoria;
use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use App\Models\Rol;
use App\Models\RolProduccion;
use App\Models\Turno;
use Illuminate\Support\Carbon;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para probar Dashboard en MariaDB aislada.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $this->travelTo(Carbon::parse('2026-10-09 10:00:00', config('app.timezone')));
    $this->fechaHoy = now()->toDateString();

    Categoria::create(['nombre_categorias' => 'Otra familia ficticia']);
    RolProduccion::create(['nombre_roles_produccion' => 'Otro rol ficticio']);
    foreach (['Primer turno ficticio', 'Segundo turno ficticio'] as $nombre) {
        Turno::create(['nombre_turnos' => $nombre]);
    }
    // Estos escenarios de negocio registran o consultan cualquier turno.
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->noche = Turno::create(['nombre_turnos' => 'Noche']);
    $this->withoutVite();
});

afterEach(function () {
    $this->travelBack();
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

function datosLoteDashboard(array $fixtures, string $fecha, int $turnoId, ?int $latas = null): array
{
    $latas ??= DetallePan::LATAS_POR_COCHE + 4;

    return [
        'categoria' => 'pan', 'fecha' => $fecha, 'turno_id' => $turnoId,
        'detalles' => [[
            'producto_id' => $fixtures['productos']['Pan']->id,
            'hora_finalizacion' => '08:35',
            'coches' => intdiv($latas, DetallePan::LATAS_POR_COCHE),
            'latas_adicionales' => $latas % DetallePan::LATAS_POR_COCHE,
            'participantes' => [
                ['empleado_id' => $fixtures['empleados'][0]->id, 'rol_produccion_id' => $fixtures['roles']['Maestro']->id],
                ['empleado_id' => $fixtures['empleados'][1]->id, 'rol_produccion_id' => $fixtures['roles']['Ayudante']->id],
            ],
        ]],
    ];
}

function crearLoteDashboard(array $fixtures, string $fecha, int $turnoId, ?int $latas = null): DetallePan
{
    $produccion = app(RegistrarProduccionPan::class)->ejecutar(
        datosLoteDashboard($fixtures, $fecha, $turnoId, $latas), $fixtures['usuario']->id,
    );

    return $produccion->detallesPan()->orderByDesc('id')->firstOrFail();
}

function hoyDashboard($response): array
{
    return $response->viewData('datosDashboard')['hoy'];
}

test('dashboard autenticado sin producción muestra ceros estados vacíos y accesos nombrados', function () {
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk()
        ->assertSeeText('Aún no hay producciones registradas.')
        ->assertSee('data-grafico-turnos', false)->assertDontSee('modal-torta', false);
    expect(hoyDashboard($response))->toBe([
        'pan' => ['latas' => 0, 'unidades' => 0, 'unidades_completas' => true, 'latas_completas' => true],
        'torta' => 0, 'bocadito' => 0, 'registros' => 0,
    ])->and($response->viewData('datosDashboard')['actividad'])->toBeEmpty();

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $accesos = $xpath->query('//nav[@aria-label="Accesos rápidos"]/a');
    expect($accesos->length)->toBe(2)
        ->and($accesos->item(0)->getAttribute('href'))->toBe(route('produccion.index'))
        ->and($accesos->item(1)->getAttribute('href'))->toBe(route('history.index'));
});

test('dashboard tolera catálogos vacíos sin exigir que Pan o turnos existan', function () {
    // Exclusivamente fixtures de la base temporal verificada en beforeEach.
    DB::table('productos')->delete();
    DB::table('categorias')->delete();
    DB::table('turnos')->delete();
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk()
        ->assertSeeText('Aún no hay producciones registradas.');
    expect(hoyDashboard($response)['registros'])->toBe(0);
});

test('dashboard cuenta lotes y suma latas de hoy sin confundirlos con sesiones ni otras fechas', function () {
    $turno = $this->fixtures['turno']->id;
    crearLoteDashboard($this->fixtures, $this->fechaHoy, $turno);
    crearLoteDashboard($this->fixtures, $this->fechaHoy, $turno, 7);
    crearLoteDashboard($this->fixtures, $this->fechaHoy, $this->noche->id, 2 * DetallePan::LATAS_POR_COCHE);
    crearLoteDashboard($this->fixtures, '2026-10-08', $turno, 300);
    crearLoteDashboard($this->fixtures, '2026-10-10', $turno, 400);
    expect(Produccion::where('fecha', $this->fechaHoy)->count())->toBe(2);

    $total = 3 * DetallePan::LATAS_POR_COCHE + 11;
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk();
    expect(hoyDashboard($response)['registros'])->toBe(3)
        ->and(hoyDashboard($response)['pan']['latas'])->toBe($total)
        ->and(end($response->viewData('datosDashboard')['series']['siete_dias'])['pan_latas'])->toBe($total);
});

test('dashboard separa las familias sin sumar detalles inconsistentes de Pan', function (string $familia, string $clase) {
    crearLoteDashboard($this->fixtures, $this->fechaHoy, $this->fixtures['turno']->id, 9);
    $otra = Produccion::create([
        'fecha' => $this->fechaHoy, 'categoria_id' => $this->fixtures['productos'][$familia]->categoria_id,
        'registrado_por_usuario_id' => $this->fixtures['usuario']->id,
    ]);
    $detalle = IsolatedMariaDb::detail($clase, $otra, $this->fixtures);
    // Una fila inconsistente en detalle_pan bajo otra familia no se cuenta como Pan.
    IsolatedMariaDb::detail(DetallePan::class, $otra, $this->fixtures, ['cantidad' => 999]);
    $hoy = hoyDashboard($this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk());
    expect($hoy['pan']['latas'])->toBe(9)
        ->and($hoy[strtolower($familia)])->toBe($detalle->cantidad)
        ->and($hoy['registros'])->toBe(2);
})->with([
    ['Torta', DetalleTorta::class], ['Bocadito', DetalleBocadito::class],
]);

test('dashboard muestra sesiones sin turno en la actividad sin perder cantidades', function () {
    $detalle = crearLoteDashboard($this->fixtures, $this->fechaHoy, $this->fixtures['turno']->id, 8);
    $detalle->produccion()->update(['turno_id' => null]);
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk()
        ->assertSeeText('Sin turno registrado');
    expect($response->viewData('datosDashboard')['actividad'][0]['turno'])->toBe('Sin turno registrado')
        ->and(hoyDashboard($response)['pan']['latas'])->toBe(8);
});

test('dashboard limita la actividad a cinco y ordena por fecha y detalle descendentes', function () {
    $ids = [];
    foreach (['2026-10-08', '2026-10-03', '2026-10-09', '2026-10-07', '2026-10-09', '2026-10-05', '2026-10-06'] as $fecha) {
        $ids[] = crearLoteDashboard($this->fixtures, $fecha, $this->fixtures['turno']->id)->id;
    }
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk()
        ->assertSeeText('Pan ficticio')->assertSeeText('Mañana');
    expect(array_column($response->viewData('datosDashboard')['actividad'], 'id'))->toBe([$ids[4], $ids[2], $ids[0], $ids[3], $ids[6]])
        ->and(substr_count($response->getContent(), 'class="actividad-fila"'))->toBe(5);
});

test('dashboard conserva lotes de productos inactivos y escapa los nombres del catálogo', function () {
    crearLoteDashboard($this->fixtures, $this->fechaHoy, $this->fixtures['turno']->id);
    $this->fixtures['productos']['Pan']->update(['activo' => false, 'nombre_p' => '<script>ejemplo</script>']);
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk()
        ->assertSee('&lt;script&gt;ejemplo&lt;/script&gt;', false)
        ->assertDontSee('<script>ejemplo</script>', false);
    expect(hoyDashboard($response)['registros'])->toBe(1);
});

test('dashboard usa la fecha Laravel al cambiar de día y conserva el historial anterior', function () {
    crearLoteDashboard($this->fixtures, '2026-10-09', $this->fixtures['turno']->id, 12);
    $this->travelTo(Carbon::parse('2026-10-10 00:01:00', config('app.timezone')));
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk()
        ->assertSeeText('10/10/2026')->assertSeeText('Pan ficticio');
    $datos = $response->viewData('datosDashboard');
    expect(end($datos['series']['siete_dias'])['fecha'])->toBe('2026-10-10')
        ->and($datos['hoy']['registros'])->toBe(0)
        ->and($datos['hoy']['pan']['latas'])->toBe(0)
        ->and($datos['actividad'])->toHaveCount(1);
});

test('la configuración por defecto y el reloj Laravel utilizan America Lima', function () {
    $entorno = Env::getRepository();
    $timezoneAnterior = $entorno->get('APP_TIMEZONE');
    try {
        $entorno->clear('APP_TIMEZONE');
        $configuracion = require config_path('app.php');
        expect($configuracion['timezone'])->toBe('America/Lima');
    } finally {
        if ($timezoneAnterior === null) {
            $entorno->clear('APP_TIMEZONE');
        } else {
            $entorno->set('APP_TIMEZONE', $timezoneAnterior);
        }
    }

    expect(config('app.timezone'))->toBe('America/Lima')
        ->and(date_default_timezone_get())->toBe('America/Lima')
        ->and(now()->getTimezone()->getName())->toBe('America/Lima')
        ->and(today()->getTimezone()->getName())->toBe('America/Lima');
});

test('hoy y la fecha por defecto siguen el día de Lima sin desplazar DATE ni filtros del historial', function () {
    $turnoId = $this->fixtures['turno']->id;
    $diaNueve = crearLoteDashboard($this->fixtures, '2026-10-09', $turnoId, 11);
    $diaDiez = crearLoteDashboard($this->fixtures, '2026-10-10', $turnoId, 23);
    $fechasOriginales = DB::table('produccion')->orderBy('id')->pluck('fecha')->all();
    $this->actingAs($this->fixtures['usuario']);

    foreach ([
        ['2026-10-10 02:00:00', '2026-10-09', $diaNueve, 11],
        ['2026-10-10 05:00:00', '2026-10-10', $diaDiez, 23],
    ] as [$instanteUtc, $fechaLocal, $lote, $latas]) {
        // Congelar el mismo instante UTC; Laravel debe resolver su fecha local.
        $this->travelTo(Carbon::parse($instanteUtc, 'UTC'));
        expect(now()->toDateString())->toBe($fechaLocal)
            ->and(today()->toDateString())->toBe($fechaLocal);

        $dashboard = $this->get(route('dashboard'))->assertOk();
        expect(end($dashboard->viewData('datosDashboard')['series']['siete_dias'])['fecha'])->toBe($fechaLocal)
            ->and(hoyDashboard($dashboard)['registros'])->toBe(1)
            ->and(hoyDashboard($dashboard)['pan']['latas'])->toBe($latas);

        $produccion = $this->get(route('produccion.index', ['turno_id' => $turnoId]))->assertOk();
        expect($produccion->viewData('fechaSeleccionada'))->toBe($fechaLocal)
            ->and($produccion->viewData('detallesRegistrados')->pluck('id')->all())->toBe([$diaDiez->id, $diaNueve->id])
            ->and($produccion->viewData('detallesRegistrados')->pluck('id')->all())->toContain($lote->id);
        $produccion->assertSee('name="fecha" value="'.$fechaLocal.'"', false);

        $historial = $this->get(route('history.index', [
            'fecha_inicio' => '2026-10-09', 'fecha_fin' => '2026-10-09',
        ]))->assertOk();
        expect($historial->viewData('detalles')->pluck('id')->all())->toBe([$diaNueve->id])
            ->and($historial->viewData('detalles')->sole()->produccion->fecha->toDateString())->toBe('2026-10-09');
    }

    expect(DB::table('produccion')->orderBy('id')->pluck('fecha')->all())->toBe($fechasOriginales)
        ->and($fechasOriginales)->toBe(['2026-10-09', '2026-10-10'])
        ->and($diaNueve->produccion()->firstOrFail()->fecha->toDateString())->toBe('2026-10-09')
        ->and($diaDiez->produccion()->firstOrFail()->fecha->toDateString())->toBe('2026-10-10');
});

test('login dashboard registro de Pan y regreso actualizan métricas e historial con datos persistidos', function () {
    $this->get(route('login'))->assertOk();
    $this->post('/login', [
        'username' => $this->fixtures['usuario']->username, 'password' => 'clave-ficticia-sprint4',
    ])->assertRedirect('dashboard');
    $this->assertAuthenticatedAs($this->fixtures['usuario']);
    $inicial = $this->get(route('dashboard'))->assertOk();
    expect(hoyDashboard($inicial)['registros'])->toBe(0);
    $this->get(route('produccion.index'))->assertOk();
    $datos = datosLoteDashboard($this->fixtures, $this->fechaHoy, $this->fixtures['turno']->id);
    $this->post(route('produccion.store'), $datos)->assertRedirect(route('produccion.index', [
        'fecha' => $this->fechaHoy, 'turno_id' => $this->fixtures['turno']->id,
    ]))->assertSessionHasNoErrors();
    $actualizado = $this->get(route('dashboard'))->assertOk()->assertSeeText('Pan ficticio');
    expect(hoyDashboard($actualizado)['registros'])->toBe(1)
        ->and(hoyDashboard($actualizado)['pan']['latas'])->toBe(DetallePan::LATAS_POR_COCHE + 4);
    $this->get(route('history.index'))->assertOk()->assertSeeText('Pan ficticio')
        ->assertSeeText((DetallePan::LATAS_POR_COCHE + 4).' latas');
});

test('dashboard mantiene consultas acotadas al pasar de uno a varios lotes recientes', function () {
    crearLoteDashboard($this->fixtures, $this->fechaHoy, $this->fixtures['turno']->id);
    $this->fixtures['usuario']->load('empleado');
    $this->actingAs($this->fixtures['usuario']);
    $medir = function (): int {
        // Cada petición independiente incluye la lectura del rol para autorizar.
        $this->fixtures['usuario']->unsetRelation('rol');
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->get(route('dashboard'))->assertOk();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    };
    $consultas = $medir();
    foreach (range(1, 9) as $numero) {
        crearLoteDashboard($this->fixtures, $this->fechaHoy, $this->fixtures['turno']->id);
    }
    // Dos lecturas agregadas preparan métricas fijas, series y actividad de tres familias.
    expect($medir())->toBe($consultas)->toBeLessThanOrEqual(11);
});
