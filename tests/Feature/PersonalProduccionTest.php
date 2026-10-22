<?php

use App\Actions\Produccion\RegistrarProduccionBocadito;
use App\Actions\Produccion\RegistrarProduccionPan;
use App\Actions\Produccion\RegistrarProduccionTorta;
use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use App\Models\Rol;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->actingAs($this->fixtures['usuario'])->withoutVite();
    Storage::fake('public');
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

dataset('variantes personal produccion', [
    ['pan', 'LATA'], ['pan', 'UNIDAD'], ['torta', 'UNIDAD'], ['bocadito', 'UNIDAD'],
]);

function payloadPersonalProduccion(array $fixtures, string $familia, string $unidad): array
{
    if ($familia === 'pan' && $unidad === 'UNIDAD') {
        $fixtures['productos']['Pan']->update(['unidad_medida_id' => UnidadMedida::where('codigo', 'UNIDAD')->sole()->id, 'panes_por_lata' => null]);
    }
    $participantes = [
        ['empleado_id' => $fixtures['empleados'][0]->id, 'rol_produccion_id' => $fixtures['roles']['Maestro']->id],
        ['empleado_id' => $fixtures['empleados'][1]->id, 'rol_produccion_id' => $fixtures['roles']['Ayudante']->id],
    ];

    return ['categoria' => $familia, 'fecha' => '2026-10-18', 'turno_id' => $fixtures['turno']->id,
        'detalles' => [['producto_id' => $fixtures['productos'][ucfirst($familia)]->id,
            'participantes' => $participantes, 'observacion' => 'Lote con personal ficticio',
            ...($familia === 'pan' && $unidad === 'LATA' ? ['coches' => 1, 'latas_adicionales' => 4, 'hora_finalizacion' => '09:35'] : ['cantidad' => 8]),
            ...($familia === 'torta' ? ['forma' => 'circular', 'hora_vitrina' => '14:15'] : [])]]];
}

function rutaPersonalProduccion(string $familia): string
{
    return $familia === 'pan' ? '/produccion' : '/produccion/'.$familia;
}

test('las cuatro variantes admiten Practicantes adicionales y preservan su función al ascender o desactivar', function (string $familia, string $unidad) {
    $practicante = $this->fixtures['empleados'][2];
    $practicante->update(['rol_produccion_id' => $this->fixtures['roles']['Practicante']->id]);
    $payload = payloadPersonalProduccion($this->fixtures, $familia, $unidad);
    $payload['detalles'][0]['participantes'][] = ['empleado_id' => $practicante->id, 'rol_produccion_id' => $practicante->rol_produccion_id];
    $this->post(rutaPersonalProduccion($familia), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $tabla = 'detalle_'.$familia.'_empleado';
    $pivotes = DB::table($tabla)->orderBy('empleado_id')->get()->toArray();
    foreach (['Ayudante', 'Maestro'] as $rolActual) {
        $this->patch('/empleados/'.$practicante->id, ['nombre_empleados' => 'Carlos Ramírez', 'rol_produccion_id' => $this->fixtures['roles'][$rolActual]->id, 'activo' => 1])
            ->assertRedirect('/empleados')->assertSessionHasNoErrors();
        expect(DB::table($tabla)->orderBy('empleado_id')->get()->toArray())->toEqual($pivotes);
        $this->get('/history')->assertOk()->assertSeeText('Practicantes: Carlos Ramírez');
    }
    $this->patch('/empleados/'.$practicante->id.'/estado', ['activo' => 0])->assertRedirect('/empleados');
    $response = $this->get('/produccion?categoria='.$familia.'&fecha=2026-10-18&turno_id='.$payload['turno_id'])->assertOk();
    expect($response->viewData('empleados')->contains('id', $practicante->id))->toBeFalse();
    $response->assertSeeText('Practicante: Carlos Ramírez');
    $this->get('/history')->assertOk()->assertSeeText('Practicantes: Carlos Ramírez');
    expect(DB::table($tabla)->orderBy('empleado_id')->get()->toArray())->toEqual($pivotes);
    $this->post(rutaPersonalProduccion($familia), $payload)->assertSessionHasErrors('detalles.0.participantes.2.empleado_id');
    $this->assertDatabaseCount('detalle_'.$familia, 1);
})->with('variantes personal produccion');

test('Practicante no sustituye al Ayudante obligatorio en ninguna variante', function (string $familia, string $unidad) {
    $this->fixtures['empleados'][1]->update(['rol_produccion_id' => $this->fixtures['roles']['Practicante']->id]);
    $payload = payloadPersonalProduccion($this->fixtures, $familia, $unidad);
    $payload['detalles'][0]['participantes'][1]['rol_produccion_id'] = $this->fixtures['roles']['Practicante']->id;
    $this->post(rutaPersonalProduccion($familia), $payload)->assertSessionHasErrors('detalles.0.participantes');
    $this->assertDatabaseCount('produccion', 0);
})->with('variantes personal produccion');

test('rol manipulado inactivo o rol pendiente se rechazan en HTTP y en la Action transaccional', function (string $familia, string $unidad, string $caso) {
    $payload = payloadPersonalProduccion($this->fixtures, $familia, $unidad);
    $campo = 'detalles.0.participantes.1.rol_produccion_id';
    if ($caso === 'inactivo') {
        $this->fixtures['empleados'][1]->update(['activo' => false]);
        $campo = 'detalles.0.participantes.1.empleado_id';
    } elseif ($caso === 'sin rol') {
        $this->fixtures['empleados'][1]->update(['rol_produccion_id' => null]);
    } else {
        // La composición enviada aparenta ser válida, pero el trabajador ahora es Practicante.
        $this->fixtures['empleados'][1]->update(['rol_produccion_id' => $this->fixtures['roles']['Practicante']->id]);
    }
    $this->post(rutaPersonalProduccion($familia), $payload)->assertSessionHasErrors($campo);
    $action = match ($familia) {
        'pan' => RegistrarProduccionPan::class, 'torta' => RegistrarProduccionTorta::class, 'bocadito' => RegistrarProduccionBocadito::class,
    };
    try {
        app($action)->ejecutar($payload, $this->fixtures['usuario']->id);
        $this->fail('La Action debía rechazar personal no disponible o rol obsoleto.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($campo);
    }
    expect(Produccion::count())->toBe(0)->and(DetallePan::count())->toBe(0)
        ->and(DetalleTorta::count())->toBe(0)->and(DetalleBocadito::count())->toBe(0);
})->with('variantes personal produccion')->with(['inactivo', 'sin rol', 'rol cambiado']);

test('nuevas selecciones solo incluyen personal activo con rol configurado y no ofrecen creación al Operador', function () {
    $this->fixtures['empleados'][1]->update(['activo' => false]);
    $this->fixtures['empleados'][2]->update(['rol_produccion_id' => null]);
    $this->fixtures['rol']->update(['nombre_roles' => Rol::OPERADOR]);
    $this->fixtures['usuario']->update(['turno_id' => $this->fixtures['turno']->id]);
    $response = $this->get('/produccion')->assertOk()->assertDontSee('href="'.route('personal.index').'"', false)->assertDontSeeText('Nuevo empleado');
    expect($response->viewData('empleados')->modelKeys())->toBe([$this->fixtures['empleados'][0]->id]);
    $response->assertSee('data-rol-id="'.$this->fixtures['roles']['Maestro']->id.'"', false);
    $this->post('/empleados', ['nombre_empleados' => 'No permitido'])->assertForbidden();
});

test('cada nueva producción copia el rol vigente durante la carrera Practicante Ayudante Maestro', function (string $familia, string $unidad) {
    $persona = $this->fixtures['empleados'][2];
    $datos = payloadPersonalProduccion($this->fixtures, $familia, $unidad);
    foreach (['Practicante', 'Ayudante', 'Maestro'] as $rol) {
        $rolId = $this->fixtures['roles'][$rol]->id;
        $this->patch('/empleados/'.$persona->id, ['nombre_empleados' => 'Carlos Ramírez', 'rol_produccion_id' => $rolId, 'activo' => 1])
            ->assertRedirect('/empleados')->assertSessionHasNoErrors();
        $payload = $datos;
        $participacion = ['empleado_id' => $persona->id, 'rol_produccion_id' => $rolId];
        if ($rol === 'Maestro') {
            $payload['detalles'][0]['participantes'][0] = $participacion;
        } else {
            $payload['detalles'][0]['participantes'][] = $participacion;
        }
        $this->post(rutaPersonalProduccion($familia), $payload)->assertRedirect()->assertSessionHasNoErrors();
    }
    expect(DB::table('detalle_'.$familia.'_empleado')->where('empleado_id', $persona->id)
        ->orderBy('detalle_'.$familia.'_id')->pluck('rol_produccion_id')->all())
        ->toBe(array_map(fn ($rol) => $this->fixtures['roles'][$rol]->id, ['Practicante', 'Ayudante', 'Maestro']));
    $this->get('/history')->assertOk()->assertSeeText('Practicantes: Carlos Ramírez')->assertSeeText('Maestro: Carlos Ramírez');
})->with('variantes personal produccion');
