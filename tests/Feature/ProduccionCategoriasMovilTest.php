<?php

use App\Actions\Produccion\RegistrarProduccionBocadito;
use App\Actions\Produccion\RegistrarProduccionPan;
use App\Actions\Produccion\RegistrarProduccionTorta;
use App\Models\Categoria;
use App\Models\GrupoProduccion;
use App\Models\Rol;
use App\Models\Turno;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere php tests/run-mariadb.php.');
    }
    IsolatedMariaDb::connect();
    Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]);
    DB::beginTransaction();
    // Desplazar IDs para comprobar resolución por relaciones/nombres reales.
    Categoria::create(['nombre_categorias' => 'Anterior']);
    Turno::create(['nombre_turnos' => 'Otro']);
    $this->f = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->actingAs($this->f['usuario'])->withoutVite();
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel()) {
            DB::rollBack();
        }
    }
});

function payloadCategoriasMovil(array $f, string $familia, int $ayudantes = 1): array
{
    $participantes = [['empleado_id' => $f['empleados'][0]->id, 'rol_produccion_id' => $f['roles']['Maestro']->id]];
    foreach (range(1, 2) as $n) {
        if ($n <= $ayudantes) {
            $participantes[] = ['empleado_id' => $f['empleados'][$n]->id, 'rol_produccion_id' => $f['roles']['Ayudante']->id];
        }
    }

    return ['categoria' => strtolower($familia), 'fecha' => '2026-10-23', 'turno_id' => $f['turno']->id,
        'detalles' => [['producto_id' => $f['productos'][$familia]->id, 'participantes' => $participantes,
            ...($familia === 'Pan' ? ['coches' => 1, 'latas_adicionales' => 2, 'hora_finalizacion' => '08:25'] : ['cantidad' => 12]),
            ...($familia === 'Torta' ? ['forma' => 'circular', 'hora_vitrina' => '10:35'] : [])]]];
}

function rutaCategoriasMovil(string $familia): string
{
    return $familia === 'Pan' ? '/produccion' : '/produccion/'.strtolower($familia);
}

test('composición por categoría guarda cantidades turno y roles o rechaza sin residuos', function (string $familia, int $ayudantes, bool $valido) {
    $datos = payloadCategoriasMovil($this->f, $familia, $ayudantes);
    $response = $this->post(rutaCategoriasMovil($familia), $datos);
    if (! $valido) {
        $response->assertSessionHasErrors('detalles.0.participantes');
        $this->assertDatabaseCount('produccion', 0);
        $this->assertDatabaseCount('detalle_'.strtolower($familia), 0);
        $action = match ($familia) {
            'Pan' => RegistrarProduccionPan::class, 'Torta' => RegistrarProduccionTorta::class, default => RegistrarProduccionBocadito::class,
        };
        expect(fn () => app($action)->ejecutar($datos, $this->f['usuario']->id))->toThrow(ValidationException::class);
        $this->assertDatabaseCount('produccion', 0);

        return;
    }
    $response->assertSessionHasNoErrors()->assertSessionHas('success', $familia === 'Torta' ? 'Torta guardada' : "$familia guardado");
    $this->assertDatabaseHas('produccion', ['turno_id' => $this->f['turno']->id, 'categoria_id' => $this->f['productos'][$familia]->categoria_id]);
    $this->assertDatabaseHas('detalle_'.strtolower($familia), ['cantidad' => $familia === 'Pan' ? 20 : 12]);
    $this->assertDatabaseCount('detalle_'.strtolower($familia).'_empleado', 1 + $ayudantes);
    foreach ($datos['detalles'][0]['participantes'] as $participante) {
        $this->assertDatabaseHas('detalle_'.strtolower($familia).'_empleado', $participante);
    }
})->with([['Pan', 1, true], ['Pan', 0, false], ['Pan', 2, true], ['Torta', 0, true], ['Torta', 1, true], ['Torta', 2, true], ['Bocadito', 1, true], ['Bocadito', 0, false], ['Bocadito', 2, false]]);

test('duplicados se rechazan en cada categoría', function (string $familia) {
    $datos = payloadCategoriasMovil($this->f, $familia);
    $datos['detalles'][0]['participantes'][] = $datos['detalles'][0]['participantes'][1];
    $this->post(rutaCategoriasMovil($familia), $datos)->assertSessionHasErrors('detalles.0.participantes');
    $this->assertDatabaseCount('produccion', 0);
})->with(['Pan', 'Torta', 'Bocadito']);

test('Noche respeta categoría y permisos incluso sin FormRequest', function (string $familia, string $rol) {
    $noche = Turno::create(['nombre_turnos' => 'Noche']);
    $this->f['rol']->update(['nombre_roles' => $rol]);
    $this->f['usuario']->update(['turno_id' => $rol === Rol::OPERADOR ? $noche->id : null]);
    $datos = payloadCategoriasMovil($this->f, $familia);
    $datos['turno_id'] = $noche->id;
    $action = match ($familia) {
        'Pan' => RegistrarProduccionPan::class, 'Torta' => RegistrarProduccionTorta::class, default => RegistrarProduccionBocadito::class,
    };
    if ($familia === 'Pan') {
        $this->post('/produccion', $datos)->assertSessionHasNoErrors();
        app($action)->ejecutar($datos, $this->f['usuario']->id);
        $this->assertDatabaseCount('detalle_pan', 2);
    } else {
        $this->post(rutaCategoriasMovil($familia), $datos)->assertSessionHasErrors('turno_id');
        expect(fn () => app($action)->ejecutar($datos, $this->f['usuario']->id))->toThrow(ValidationException::class);
        $this->assertDatabaseCount('produccion', 0);
    }
})->with(['Pan', 'Torta', 'Bocadito'])->with([Rol::ADMINISTRADOR, Rol::OPERADOR]);

test('propuestas usan grupos activos aptos y relación de categoría sin excluir empleados externos', function () {
    foreach (['Pan', 'Torta', 'Bocadito'] as $familia) {
        $grupo = GrupoProduccion::create(['nombre' => 'Equipo '.$familia, 'categoria_id' => $this->f['productos'][$familia]->categoria_id, 'maestro_id' => $this->f['empleados'][0]->id, 'activo' => true]);
        if ($familia !== 'Torta') {
            $grupo->integrantes()->attach($this->f['empleados'][1]->id);
        }
    }
    $inactivo = GrupoProduccion::create(['nombre' => 'Inactivo', 'categoria_id' => $this->f['productos']['Torta']->categoria_id, 'maestro_id' => $this->f['empleados'][0]->id, 'activo' => false]);
    $obsoleto = GrupoProduccion::create(['nombre' => 'Obsoleto', 'categoria_id' => $this->f['productos']['Pan']->categoria_id, 'maestro_id' => $this->f['empleados'][2]->id, 'activo' => true]);
    $response = $this->get('/produccion?categoria=torta')->assertOk();
    expect($response->viewData('gruposDisponibles')->pluck('nombre')->all())->toBe(['Equipo Bocadito', 'Equipo Pan', 'Equipo Torta'])
        ->and($response->viewData('categoriasPorEmpleado')[$this->f['empleados'][1]->id])->toBe(['bocadito', 'pan'])
        ->and($response->viewData('empleados')->modelKeys())->toContain($this->f['empleados'][2]->id)
        ->and($response->viewData('turnoSeleccionadoId'))->toBe($this->f['turno']->id);
    // El vínculo de un grupo no limita la selección manual en otra categoría.
    $datos = payloadCategoriasMovil($this->f, 'Torta');
    $datos['detalles'][0]['participantes'][1]['empleado_id'] = $this->f['empleados'][2]->id;
    $this->post('/produccion/torta', $datos)->assertSessionHasNoErrors();
});

test('Torta funciona con Maestro sin catálogo ni empleados Ayudantes disponibles', function () {
    $this->f['empleados'][1]->update(['rol_produccion_id' => null]);
    $this->f['empleados'][2]->update(['rol_produccion_id' => null]);
    $this->f['roles']['Ayudante']->delete();
    $this->get('/produccion?categoria=torta')->assertOk()->assertDontSeeText('Faltan los roles Maestro o Ayudante.');
    $this->post('/produccion/torta', payloadCategoriasMovil($this->f, 'Torta', 0))->assertSessionHasNoErrors();
    $this->assertDatabaseCount('detalle_torta', 1);
});
