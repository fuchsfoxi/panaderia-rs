<?php

use App\Actions\Produccion\RegistrarProduccionPan;
use App\Models\Categoria;
use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use App\Models\RolProduccion;
use App\Models\Turno;
use Illuminate\Support\Carbon;
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
    $this->fixtures = IsolatedMariaDb::fixtures();
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

test('dashboard autenticado sin producción muestra ceros estados vacíos y accesos nombrados', function () {
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk()
        ->assertSeeText('No hay producción registrada hoy.')
        ->assertSeeText('Aún no hay lotes de Pan registrados.')
        ->assertDontSee('<canvas', false)->assertDontSee('modal-torta', false);
    expect($response->viewData('totalLotesHoy'))->toBe(0)
        ->and($response->viewData('totalLatasHoy'))->toBe(0)
        ->and($response->viewData('ultimasProducciones'))->toBeEmpty();

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
        ->assertSeeText('No hay turnos disponibles.')->assertSeeText('No hay producción registrada hoy.');
    expect($response->viewData('totalLotesHoy'))->toBe(0)
        ->and($response->viewData('produccionPorTurno'))->toBeEmpty();
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
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk()
        ->assertDontSeeText('No hay producción registrada hoy.');
    expect($response->viewData('totalLotesHoy'))->toBe(3)
        ->and($response->viewData('totalLatasHoy'))->toBe($total);
    $response->assertSee('data-metrica="lotes">3<', false)
        ->assertSee('data-metrica="latas">'.$total.'<', false);
});

test('dashboard excluye otras familias de métricas turnos y últimas producciones', function (string $familia, string $clase) {
    $pan = crearLoteDashboard($this->fixtures, $this->fechaHoy, $this->fixtures['turno']->id, 9);
    $otra = Produccion::create([
        'fecha' => $this->fechaHoy, 'categoria_id' => $this->fixtures['productos'][$familia]->categoria_id,
        'registrado_por_usuario_id' => $this->fixtures['usuario']->id,
    ]);
    IsolatedMariaDb::detail($clase, $otra, $this->fixtures);
    // Una fila inconsistente en detalle_pan tampoco habilita una sesión de otra familia.
    IsolatedMariaDb::detail(DetallePan::class, $otra, $this->fixtures, ['cantidad' => 999]);
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk();
    expect($response->viewData('totalLotesHoy'))->toBe(1)
        ->and($response->viewData('totalLatasHoy'))->toBe(9)
        ->and($response->viewData('ultimasProducciones')->pluck('id')->all())->toBe([$pan->id])
        ->and($response->viewData('produccionPorTurno')->sum('lotes'))->toBe(1);
})->with([
    ['Torta', DetalleTorta::class], ['Bocadito', DetalleBocadito::class],
]);

test('dashboard resume turnos del catálogo con IDs arbitrarios y usa la cabecera sobre el detalle legacy', function () {
    expect($this->fixtures['turno']->id)->toBeGreaterThan(2)
        ->and($this->noche->id)->toBeGreaterThan(2)
        ->and($this->fixtures['productos']['Pan']->categoria_id)->not->toBe(1);
    $primero = crearLoteDashboard($this->fixtures, $this->fechaHoy, $this->fixtures['turno']->id, 10);
    $primero->update(['turno_id' => $this->noche->id]);
    crearLoteDashboard($this->fixtures, $this->fechaHoy, $this->fixtures['turno']->id, 11);
    crearLoteDashboard($this->fixtures, $this->fechaHoy, $this->noche->id, 7);
    crearLoteDashboard($this->fixtures, '2026-10-08', $this->noche->id, 90);
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk();
    $turnos = $response->viewData('produccionPorTurno')->keyBy('nombre');
    expect($turnos->get('Mañana'))->toBe(['nombre' => 'Mañana', 'lotes' => 2, 'latas' => 21])
        ->and($turnos->get('Noche'))->toBe(['nombre' => 'Noche', 'lotes' => 1, 'latas' => 7])
        ->and($turnos->get('Primer turno ficticio'))->toBe(['nombre' => 'Primer turno ficticio', 'lotes' => 0, 'latas' => 0])
        ->and($turnos->sum('latas'))->toBe($response->viewData('totalLatasHoy'));
});

test('dashboard identifica sesiones sin turno sin inventarlo ni perder cantidades', function () {
    $detalle = crearLoteDashboard($this->fixtures, $this->fechaHoy, $this->fixtures['turno']->id, 8);
    $detalle->produccion()->update(['turno_id' => null]);
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk()
        ->assertSeeText('Sin turno registrado');
    expect($response->viewData('produccionPorTurno')->last())->toBe([
        'nombre' => 'Sin turno registrado', 'lotes' => 1, 'latas' => 8,
    ])->and($response->viewData('totalLatasHoy'))->toBe(8);
});

test('dashboard limita las últimas producciones a cinco y ordena por fecha y detalle descendentes', function () {
    $ids = [];
    foreach (['2026-10-08', '2026-10-03', '2026-10-09', '2026-10-07', '2026-10-09', '2026-10-05', '2026-10-06'] as $fecha) {
        $ids[] = crearLoteDashboard($this->fixtures, $fecha, $this->fixtures['turno']->id)->id;
    }
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk()
        ->assertSeeText('Pan ficticio')->assertSeeText('Mañana')
        ->assertSeeText($this->fixtures['usuario']->username)->assertSeeText('Registró la sesión');
    $ultimas = $response->viewData('ultimasProducciones');
    expect($ultimas->pluck('id')->all())->toBe([$ids[4], $ids[2], $ids[0], $ids[3], $ids[6]])
        ->and(substr_count($response->getContent(), 'data-detalle-id='))->toBe(5);
    foreach ($ultimas as $detalle) {
        expect($detalle->relationLoaded('producto'))->toBeTrue()
            ->and($detalle->relationLoaded('produccion'))->toBeTrue()
            ->and($detalle->produccion->relationLoaded('turno'))->toBeTrue()
            ->and($detalle->produccion->relationLoaded('usuario'))->toBeTrue();
    }
});

test('dashboard conserva lotes de productos inactivos y escapa los nombres del catálogo', function () {
    crearLoteDashboard($this->fixtures, $this->fechaHoy, $this->fixtures['turno']->id);
    $this->fixtures['productos']['Pan']->update(['activo' => false, 'nombre_p' => '<script>ejemplo</script>']);
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk()
        ->assertSee('&lt;script&gt;ejemplo&lt;/script&gt;', false)
        ->assertDontSee('<script>ejemplo</script>', false);
    expect($response->viewData('totalLotesHoy'))->toBe(1);
});

test('dashboard usa la fecha Laravel al cambiar de día y conserva el historial anterior', function () {
    crearLoteDashboard($this->fixtures, '2026-10-09', $this->fixtures['turno']->id, 12);
    $this->travelTo(Carbon::parse('2026-10-10 00:01:00', config('app.timezone')));
    $response = $this->actingAs($this->fixtures['usuario'])->get(route('dashboard'))->assertOk()
        ->assertSeeText('10/10/2026')->assertSeeText('No hay producción registrada hoy.')
        ->assertSeeText('Pan ficticio');
    expect($response->viewData('fechaHoy')->toDateString())->toBe('2026-10-10')
        ->and($response->viewData('totalLotesHoy'))->toBe(0)
        ->and($response->viewData('totalLatasHoy'))->toBe(0)
        ->and($response->viewData('ultimasProducciones'))->toHaveCount(1);
});

test('login dashboard registro de Pan y regreso actualizan métricas e historial con datos persistidos', function () {
    $this->get(route('login'))->assertOk();
    $this->post('/login', [
        'username' => $this->fixtures['usuario']->username, 'password' => 'clave-ficticia-sprint4',
    ])->assertRedirect('dashboard');
    $this->assertAuthenticatedAs($this->fixtures['usuario']);
    $inicial = $this->get(route('dashboard'))->assertOk();
    expect($inicial->viewData('totalLotesHoy'))->toBe(0);
    $this->get(route('produccion.index'))->assertOk();
    $datos = datosLoteDashboard($this->fixtures, $this->fechaHoy, $this->fixtures['turno']->id);
    $this->post(route('produccion.store'), $datos)->assertRedirect(route('produccion.index', [
        'fecha' => $this->fechaHoy, 'turno_id' => $this->fixtures['turno']->id,
    ]))->assertSessionHasNoErrors();
    $actualizado = $this->get(route('dashboard'))->assertOk()->assertSeeText('Pan ficticio');
    expect($actualizado->viewData('totalLotesHoy'))->toBe(1)
        ->and($actualizado->viewData('totalLatasHoy'))->toBe(DetallePan::LATAS_POR_COCHE + 4);
    $this->get(route('history.index'))->assertOk()->assertSeeText('Pan ficticio')
        ->assertSeeText((DetallePan::LATAS_POR_COCHE + 4).' latas en total');
});

test('dashboard mantiene consultas acotadas al pasar de uno a varios lotes recientes', function () {
    crearLoteDashboard($this->fixtures, $this->fechaHoy, $this->fixtures['turno']->id);
    $this->fixtures['usuario']->load('empleado');
    $this->actingAs($this->fixtures['usuario']);
    $medir = function (): int {
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
    expect($medir())->toBe($consultas)->toBeLessThanOrEqual(8);
});
