<?php

use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\UnidadMedida;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB aislada en /tmp.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $this->f = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->directa = UnidadMedida::where('codigo', 'UNIDAD')->sole();
    $this->travelTo(Carbon::parse('2027-01-02 10:00:00', 'America/Lima'));
    $this->actingAs($this->f['usuario']);
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

function loteCalculo(array $f, string $familia, string $fecha, array $extra = []): mixed
{
    $produccion = Produccion::create(['fecha' => $fecha, 'categoria_id' => $f['productos'][$familia]->categoria_id,
        'turno_id' => $f['turno']->id, 'registrado_por_usuario_id' => $f['usuario']->id]);
    $modelo = match ($familia) {
        'Pan' => DetallePan::class, 'Torta' => DetalleTorta::class, 'Bocadito' => DetalleBocadito::class
    };

    return IsolatedMariaDb::detail($modelo, $produccion, $f, $extra);
}

test('totales fijos, unidades históricas, familias y series no dependen del filtro', function () {
    loteCalculo($this->f, 'Pan', '2027-01-02', ['cantidad' => 2, 'panes_por_lata_usado' => 10, 'hora_finalizacion' => '08:30']);
    loteCalculo($this->f, 'Pan', '2027-01-02', ['cantidad' => 3, 'panes_por_lata_usado' => 20]);
    loteCalculo($this->f, 'Pan', '2027-01-02', ['cantidad' => 7, 'unidad_medida_id' => $this->directa->id]);
    loteCalculo($this->f, 'Torta', '2027-01-02', ['cantidad' => 4, 'hora_vitrina' => '09:15']);
    loteCalculo($this->f, 'Bocadito', '2027-01-02', ['cantidad' => 25]);
    loteCalculo($this->f, 'Pan', '2026-12-30', ['cantidad' => 99, 'panes_por_lata_usado' => 2]);
    loteCalculo($this->f, 'Bocadito', '2027-01-03', ['cantidad' => 99]);
    $this->f['productos']['Pan']->update(['panes_por_lata' => 500, 'activo' => false]);
    $datos = $this->get('/dashboard?fecha_inicio=2026-12-01&fecha_fin=2026-12-31')->assertOk()->viewData('datosDashboard');
    expect($datos['hoy'])->toBe(['pan' => ['latas' => 5, 'unidades' => 87, 'unidades_completas' => true, 'latas_completas' => true], 'torta' => 4, 'bocadito' => 25, 'registros' => 5]);
    expect($datos['mensual']['pan_unidades']['productos'][0]['cantidad'])->toBe(87)
        ->and($datos['mensual']['pan_latas']['productos'][0]['cantidad'])->toBe(5)
        ->and($datos['mensual']['torta'][0]['cantidad'])->toBe(4)
        ->and($datos['mensual']['bocadito'][0]['cantidad'])->toBe(124);
    expect($datos['series']['siete_dias'])->toHaveCount(7)
        ->and($datos['series']['siete_dias'][0]['fecha'])->toBe('2026-12-27')
        ->and($datos['series']['siete_dias'][3])->toBe(['fecha' => '2026-12-30', 'pan' => 1, 'torta' => 0, 'bocadito' => 0])
        ->and($datos['series']['siete_dias'][6])->toBe(['fecha' => '2027-01-02', 'pan' => 3, 'torta' => 1, 'bocadito' => 1])
        ->and($datos['series']['mes_actual'])->toHaveCount(31)
        ->and($datos['series']['mes_actual'][2]['bocadito'])->toBe(1);
});

test('factores ausentes marcan totales incompletos y no entregan ranking definitivo', function () {
    loteCalculo($this->f, 'Pan', '2027-01-02', ['cantidad' => 3, 'panes_por_lata_usado' => null]);
    loteCalculo($this->f, 'Pan', '2027-01-02', ['cantidad' => 5, 'unidad_medida_id' => $this->directa->id]);
    $datos = $this->get('/dashboard')->assertOk()->viewData('datosDashboard');
    expect($datos['hoy']['pan'])->toBe(['latas' => 3, 'unidades' => 5, 'unidades_completas' => false, 'latas_completas' => true])
        ->and($datos['mensual']['pan_unidades'])->toBe(['completo' => false, 'productos' => []])
        ->and($datos['mensual']['pan_latas']['productos'][0]['cantidad'])->toBe(3);
});

test('rankings limitan a tres productos con desempate estable e incluyen inactivos', function () {
    $ids = [];
    foreach ([4, 9, 9, 2] as $i => $cantidad) {
        $p = Producto::create(['nombre_p' => 'Pan prueba '.$i, 'categoria_id' => $this->f['productos']['Pan']->categoria_id,
            'unidad_medida_id' => $this->f['unidad']->id, 'panes_por_lata' => 10, 'activo' => $i !== 1]);
        $ids[] = $p->id;
        loteCalculo($this->f, 'Pan', '2027-01-01', ['producto_id' => $p->id, 'cantidad' => $cantidad, 'panes_por_lata_usado' => 10]);
    }
    $datos = $this->get('/dashboard')->assertOk()->viewData('datosDashboard');
    expect(array_column($datos['mensual']['pan_latas']['productos'], 'producto_id'))->toBe([$ids[1], $ids[2], $ids[0]])
        ->and($datos['mensual']['torta'])->toBe([])->and($datos['mensual']['bocadito'])->toBe([]);
});

test('actividad usa fecha y desempate estable con horas reales o ausentes', function () {
    loteCalculo($this->f, 'Pan', '2027-01-01', ['hora_finalizacion' => '08:30']);
    loteCalculo($this->f, 'Torta', '2027-01-02', ['hora_vitrina' => '09:15']);
    loteCalculo($this->f, 'Bocadito', '2027-01-02');
    loteCalculo($this->f, 'Pan', '2027-01-02', ['unidad_medida_id' => $this->directa->id, 'hora_finalizacion' => null]);
    loteCalculo($this->f, 'Pan', '2027-01-02', ['hora_finalizacion' => '11:30']);
    loteCalculo($this->f, 'Pan', '2026-12-29');
    $datos = $this->get('/dashboard')->assertOk()->viewData('datosDashboard');
    $act = $datos['actividad'];
    expect($act)->toHaveCount(5)
        ->and(array_column($act, 'categoria'))->toBe(['bocadito', 'pan', 'pan', 'torta', 'pan'])
        ->and($act[0]['hora_etiqueta'])->toBe('Sin hora registrada')
        ->and($act[1]['tipo_hora'])->toBe('finalizacion')->and($act[1]['hora'])->toBe('11:30')
        ->and($act[2]['hora'])->toBeNull()->and($act[3]['tipo_hora'])->toBe('vitrina')->and($act[3]['hora'])->toBe('09:15');
});

test('el instante UTC cruza el año según Lima y las series completan ceros', function () {
    loteCalculo($this->f, 'Torta', '2026-12-31', ['cantidad' => 2]);
    $this->travelTo(Carbon::parse('2027-01-01 04:30:00', 'UTC'));
    $a = $this->get('/dashboard')->assertOk()->viewData('datosDashboard');
    expect($a['hoy']['torta'])->toBe(2)->and(end($a['series']['mes_actual'])['fecha'])->toBe('2026-12-31');
    $this->travelTo(Carbon::parse('2027-01-01 05:30:00', 'UTC'));
    $b = $this->get('/dashboard')->assertOk()->viewData('datosDashboard');
    expect($b['hoy']['torta'])->toBe(0)->and($b['series']['mes_actual'])->toHaveCount(31)
        ->and($b['series']['mes_actual'][0])->toBe(['fecha' => '2027-01-01', 'pan' => 0, 'torta' => 0, 'bocadito' => 0]);
});

test('rankings de torta y bocadito suman por producto sin mezclar familias y respetan empate', function () {
    foreach (['Torta', 'Bocadito'] as $familia) {
        $ids = [];
        foreach ([5, 9, 9, 3] as $i => $cantidad) {
            $p = Producto::create(['nombre_p' => $familia.' prueba '.$i, 'categoria_id' => $this->f['productos'][$familia]->categoria_id,
                'unidad_medida_id' => $this->directa->id, 'activo' => $i !== 1]);
            $ids[] = $p->id;
            loteCalculo($this->f, $familia, '2027-01-01', ['producto_id' => $p->id, 'cantidad' => $cantidad]);
        }
        loteCalculo($this->f, $familia, '2027-01-02', ['producto_id' => $ids[0], 'cantidad' => 5]);
        $datos = $this->get('/dashboard')->assertOk()->viewData('datosDashboard');
        expect(array_column($datos['mensual'][strtolower($familia)], 'producto_id'))->toBe([$ids[0], $ids[1], $ids[2]])
            ->and(array_column($datos['mensual'][strtolower($familia)], 'cantidad'))->toBe([10, 9, 9]);
    }
});

test('factores incompletos de mes anterior afectan solo la semana, no el mes actual', function () {
    loteCalculo($this->f, 'Pan', '2026-12-31', ['cantidad' => 4, 'panes_por_lata_usado' => null]);
    loteCalculo($this->f, 'Pan', '2027-01-02', ['cantidad' => 2, 'panes_por_lata_usado' => 10]);
    $datos = $this->get('/dashboard')->assertOk()->viewData('datosDashboard');
    expect($datos['hoy']['pan']['unidades_completas'])->toBeTrue()
        ->and($datos['mensual']['pan_unidades']['completo'])->toBeTrue()
        ->and($datos['mensual']['pan_unidades']['productos'][0]['cantidad'])->toBe(20)
        ->and($datos['series']['siete_dias'][4]['pan'])->toBe(1);
});

test('una unidad Pan histórica no identificada no se suma ni declara completo el cálculo', function () {
    $otra = UnidadMedida::create(['nombre_unidades_medida' => 'Unidad histórica sin identificar', 'equivalencia_unidades' => 1]);
    loteCalculo($this->f, 'Pan', '2027-01-02', ['cantidad' => 9, 'unidad_medida_id' => $otra->id]);
    $datos = $this->get('/dashboard')->assertOk()->viewData('datosDashboard');
    expect($datos['hoy']['pan'])->toBe(['latas' => 0, 'unidades' => 0, 'unidades_completas' => false, 'latas_completas' => false])
        ->and($datos['mensual']['pan_latas'])->toBe(['completo' => false, 'productos' => []])
        ->and($datos['mensual']['pan_unidades'])->toBe(['completo' => false, 'productos' => []]);
});

test('dashboard conserva denegación para invitado y rol desconocido', function () {
    auth()->logout();
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->f['rol']->update(['nombre_roles' => 'Desconocido']);
    $this->actingAs($this->f['usuario']->fresh())->get('/dashboard')->assertForbidden();
});

test('cuenta detalles de una misma cabecera y conserva turno ausente en actividad', function () {
    $primero = loteCalculo($this->f, 'Pan', '2027-01-02', ['cantidad' => 3, 'panes_por_lata_usado' => 12]);
    IsolatedMariaDb::detail(DetallePan::class, $primero->produccion, $this->f, ['cantidad' => 2, 'panes_por_lata_usado' => 12]);
    $primero->produccion->update(['turno_id' => null]);
    $datos = $this->get('/dashboard')->assertOk()->viewData('datosDashboard');
    expect($datos['hoy']['registros'])->toBe(2)->and($datos['hoy']['pan']['unidades'])->toBe(60)
        ->and($datos['actividad'][0]['turno'])->toBe('Sin turno registrado');
});

test('el mes completo incluye día bisiesto y ceros sin fabricar registros', function () {
    $this->travelTo(Carbon::parse('2028-02-28 12:00:00', 'America/Lima'));
    loteCalculo($this->f, 'Torta', '2028-02-29', ['cantidad' => 2]);
    $datos = $this->get('/dashboard')->assertOk()->viewData('datosDashboard');
    expect($datos['hoy']['registros'])->toBe(0)->and($datos['series']['mes_actual'])->toHaveCount(29)
        ->and(end($datos['series']['mes_actual']))->toBe(['fecha' => '2028-02-29', 'pan' => 0, 'torta' => 1, 'bocadito' => 0])
        ->and($datos['mensual']['torta'][0]['cantidad'])->toBe(2);
});

test('la conversión utiliza precisión entera superior al límite de 32 bits', function () {
    loteCalculo($this->f, 'Pan', '2027-01-02', ['cantidad' => 2147483647, 'panes_por_lata_usado' => 2]);
    $datos = $this->get('/dashboard')->assertOk()->viewData('datosDashboard');
    expect($datos['hoy']['pan']['unidades'])->toBe(4294967294)
        ->and($datos['mensual']['pan_unidades']['productos'][0]['cantidad'])->toBe(4294967294);
});
