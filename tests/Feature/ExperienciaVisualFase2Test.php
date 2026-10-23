<?php

use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\GrupoProduccion;
use App\Models\Produccion;
use App\Models\Rol;
use App\Models\Turno;
use App\Models\UnidadMedida;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere el runner MariaDB desechable.');
    }
    IsolatedMariaDb::connect();
    Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]);
    DB::beginTransaction();
    $this->f = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->noche = Turno::create(['nombre_turnos' => 'Noche']);
    $this->actingAs($this->f['usuario'])->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
});
afterEach(function () {
    $this->travelBack();
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel()) {
            DB::rollBack();
        }
    }
});

function loteVisualFase2(array $f, string $familia = 'Pan', string $fecha = '2026-10-23', ?int $turno = null, array $atributos = [])
{
    $sesion = Produccion::firstOrCreate(['fecha' => $fecha, 'categoria_id' => $f['productos'][$familia]->categoria_id, 'turno_id' => $turno ?? $f['turno']->id], ['registrado_por_usuario_id' => $f['usuario']->id]);
    $modelo = match ($familia) {
        'Pan' => DetallePan::class, 'Torta' => DetalleTorta::class, default => DetalleBocadito::class
    };
    $detalle = IsolatedMariaDb::detail($modelo, $sesion, $f, $atributos + ['registrado_por_usuario_id' => $f['usuario']->id, 'observacion' => 'Observación conservada', 'hora_vitrina' => '14:36', 'hora_finalizacion' => '02:36', 'panes_por_lata_usado' => 12]);
    $detalle->empleados()->attach($f['empleados'][0]->id, ['rol_produccion_id' => $f['roles']['Maestro']->id]);
    if ($familia !== 'Torta') {
        $detalle->empleados()->attach($f['empleados'][1]->id, ['rol_produccion_id' => $f['roles']['Ayudante']->id]);
    }

    return $detalle;
}

test('consulta independiente filtra fecha turno y familia con las mismas identidades que Historial', function (string $categoria, string $turno, int $total) {
    foreach (['Pan', 'Torta', 'Bocadito'] as $familia) {
        loteVisualFase2($this->f, $familia);
    }
    loteVisualFase2($this->f, 'Pan', '2026-10-23', $this->noche->id);
    loteVisualFase2($this->f, 'Pan', '2026-10-22');
    $filtros = ['fecha_inicio' => '2026-10-23', 'fecha_fin' => '2026-10-23', 'categoria' => $categoria, 'turno_id' => $turno === 'manana' ? $this->f['turno']->id : ($turno === 'noche' ? $this->noche->id : '')];
    $response = $this->get(route('produccion.registros', $filtros))->assertOk()->assertSee('data-resultados-registros', false)->assertDontSee('data-eliminar-registro', false)->assertDontSee('id="formulario-produccion"', false);
    $historial = $this->get(route('history.index', $filtros))->assertOk();
    $identidad = fn ($detalle) => get_class($detalle).':'.$detalle->id;
    expect($response->viewData('detalles')->total())->toBe($total)
        ->and($response->viewData('detalles')->map($identidad)->all())->toBe($historial->viewData('detalles')->map($identidad)->all());
})->with([['', '', 4], ['pan', '', 2], ['torta', '', 1], ['bocadito', 'manana', 1], ['', 'noche', 1], ['torta', 'noche', 0]]);

test('consulta independiente pagina y conserva los filtros sin tocar registros', function () {
    foreach (range(1, 17) as $n) {
        loteVisualFase2($this->f);
    }
    $filtros = ['fecha_inicio' => '2026-10-23', 'fecha_fin' => '2026-10-23', 'categoria' => 'pan', 'page' => 2];
    $response = $this->get(route('produccion.registros', $filtros))->assertOk()->assertSeeText('Página 2 de 2');
    expect($response->viewData('detalles')->total())->toBe(17)->and($response->viewData('detalles'))->toHaveCount(2);
    $this->assertDatabaseCount('detalle_pan', 17);
});

test('consulta independiente rechaza filtros malformados sin consultar tarjetas ajenas', function (array $filtros) {
    loteVisualFase2($this->f);
    $response = $this->get(route('produccion.registros', $filtros))->assertOk()->assertSeeText('Revisa los filtros indicados.')->assertDontSee('data-detalle-registrado=', false);
    expect($response->viewData('detalles'))->toBeEmpty();
})->with([[['fecha_inicio' => ['incorrecto']]], [['turno_id' => 999999]], [['categoria' => ['pan']]], [['fecha_inicio' => '2026-10-23', 'fecha_fin' => '2026-10-22']], [['page' => 0]]]);

test('Dashboard filtra periodo real sin sumar unidades o familias distintas como latas', function () {
    loteVisualFase2($this->f, 'Pan', '2026-10-22', null, ['cantidad' => 18]);
    loteVisualFase2($this->f, 'Pan', '2026-10-23', null, ['cantidad' => 20, 'unidad_medida_id' => UnidadMedida::where('codigo', 'UNIDAD')->sole()->id, 'panes_por_lata_usado' => null, 'hora_finalizacion' => null]);
    loteVisualFase2($this->f, 'Pan', '2026-10-24', null, ['cantidad' => 12]);
    loteVisualFase2($this->f, 'Torta');
    $response = $this->get('/dashboard?fecha_inicio=2026-10-22&fecha_fin=2026-10-23')->assertOk()->assertSeeText('Resumen del periodo')->assertSee('data-grafico-turnos', false);
    expect($response->viewData('totalLotesHoy'))->toBe(2)->and($response->viewData('totalLatasHoy'))->toBe(18)
        ->and($response->viewData('ultimasProducciones'))->toHaveCount(2)->and($response->viewData('produccionPorTurno')->sum('latas'))->toBe(18);
});

test('Dashboard muestra error de periodo invalido sin agregar datos de otras fechas', function () {
    loteVisualFase2($this->f);
    $response = $this->get('/dashboard?fecha_inicio=2026-10-24&fecha_fin=2026-10-22')->assertOk()->assertSeeText('Revisa el periodo indicado.');
    expect($response->viewData('totalLotesHoy'))->toBe(0)->and($response->viewData('totalLatasHoy'))->toBe(0);
});

test('observación desplegable conserva old input error y persistencia del lote', function () {
    $datos = ['categoria' => 'torta', 'fecha' => '2026-10-23', 'turno_id' => $this->f['turno']->id, 'detalles' => [['producto_id' => $this->f['productos']['Torta']->id, 'cantidad' => 0, 'forma' => 'circular', 'hora_vitrina' => '14:36', 'observacion' => 'Texto que no se debe perder', 'participantes' => [['empleado_id' => $this->f['empleados'][0]->id, 'rol_produccion_id' => $this->f['roles']['Maestro']->id]]]]];
    $this->from('/produccion?categoria=torta')->post('/produccion/torta', $datos)->assertSessionHasErrors('detalles.0.cantidad');
    $response = $this->get('/produccion?categoria=torta')->assertOk()->assertSeeText('Texto que no se debe perder');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    expect((new DOMXPath($dom))->query('//details[@data-observacion and @open]')->length)->toBe(1);
    $datos['detalles'][0]['cantidad'] = 2;
    $this->post('/produccion/torta', $datos)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('detalle_torta', ['observacion' => 'Texto que no se debe perder', 'hora_vitrina' => '14:36:00']);
});

test('consulta independiente mantiene permisos operativos e invitado no accede', function () {
    $this->f['rol']->update(['nombre_roles' => Rol::OPERADOR]);
    $this->actingAs($this->f['usuario']->fresh())->get('/produccion/registros')->assertOk();
    $this->f['rol']->update(['nombre_roles' => 'Desconocido']);
    $this->actingAs($this->f['usuario']->fresh())->get('/produccion/registros')->assertForbidden();
    auth()->logout();
    $this->get('/produccion/registros')->assertRedirect(route('login'));
});

test('exporta vistas ficticias de Fase 2 con recursos compilados sin Vite de desarrollo', function () {
    $this->withVite();
    app(Vite::class)->useHotFile('/tmp/fase2-sin-vite.hot');
    foreach (['Pan', 'Torta', 'Bocadito'] as $familia) {
        $grupo = GrupoProduccion::create(['nombre' => 'Equipo '.$familia, 'categoria_id' => $this->f['productos'][$familia]->categoria_id, 'maestro_id' => $this->f['empleados'][0]->id, 'activo' => true]);
        if ($familia !== 'Torta') {
            $grupo->integrantes()->attach($this->f['empleados'][1]->id);
        }
        loteVisualFase2($this->f, $familia);
    }
    loteVisualFase2($this->f, 'Pan', '2026-10-23', $this->noche->id, ['cantidad' => 23]);
    loteVisualFase2($this->f, 'Pan', '2026-10-22', null, ['cantidad' => 12]);
    foreach (['pan' => '/produccion?fecha=2026-10-23&turno_id='.$this->f['turno']->id, 'torta' => '/produccion?categoria=torta', 'bocadito' => '/produccion?categoria=bocadito', 'history' => '/history', 'dashboard' => '/dashboard', 'registros' => '/produccion/registros?fecha_inicio=2026-10-23&fecha_fin=2026-10-23', 'dashboard-periodo' => '/dashboard?fecha_inicio=2026-10-22&fecha_fin=2026-10-24', 'registros-torta' => '/produccion/registros?fecha_inicio=2026-10-23&fecha_fin=2026-10-23&categoria=torta', 'registros-noche' => '/produccion/registros?fecha_inicio=2026-10-23&fecha_fin=2026-10-23&turno_id='.$this->noche->id, 'registros-vacio' => '/produccion/registros?fecha_inicio=2026-10-21&fecha_fin=2026-10-21', 'personal' => '/empleados'] as $nombre => $ruta) {
        $response = $this->get($ruta)->assertOk();
        if (! str_starts_with($nombre, 'registros')) {
            $response->assertSee('/build/assets/', false)->assertDontSee('@vite/client', false);
        }
        if ($directorio = getenv('FASE2_BROWSER_HTML')) {
            if (! str_starts_with($directorio, '/tmp/')) {
                throw new RuntimeException('Exportar únicamente fixtures en /tmp.');
            }
            file_put_contents($directorio.'/'.$nombre.'.html', preg_replace('/(name="_token" value=")[^"]*"/', '$1csrf-ficticio"', $response->getContent()));
        }
    }
});
