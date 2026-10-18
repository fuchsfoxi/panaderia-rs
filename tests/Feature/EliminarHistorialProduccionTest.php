<?php

use App\Actions\Produccion\EliminarRegistroProduccion;
use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use App\Models\Rol;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar con el runner MariaDB aislado.');
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

function registroParaEliminar(array $fixtures, string $familia = 'pan', ?Produccion $cabecera = null): Model
{
    $cabecera ??= Produccion::create([
        'fecha' => '2026-10-18', 'categoria_id' => $fixtures['productos'][ucfirst($familia)]->categoria_id,
        'turno_id' => $fixtures['turno']->id, 'registrado_por_usuario_id' => $fixtures['usuario']->id,
    ]);
    $modelo = match ($familia) {
        'pan' => DetallePan::class, 'torta' => DetalleTorta::class, 'bocadito' => DetalleBocadito::class,
    };
    $detalle = IsolatedMariaDb::detail($modelo, $cabecera, $fixtures);
    $detalle->empleados()->attach($fixtures['empleados'][0]->id, ['rol_produccion_id' => $fixtures['roles']['Maestro']->id]);
    $detalle->empleados()->attach($fixtures['empleados'][1]->id, ['rol_produccion_id' => $fixtures['roles']['Ayudante']->id]);

    return $detalle;
}

function exigirCsrfHistorial(): void
{
    $middleware = Mockery::mock(PreventRequestForgery::class, [app(), app('encrypter')])
        ->makePartial()->shouldAllowMockingProtectedMethods();
    $middleware->shouldReceive('runningUnitTests')->andReturnFalse();
    app()->instance(PreventRequestForgery::class, $middleware);
}

test('Administrador elimina el detalle confirmado y su cabecera vacía sin tocar catálogos', function (string $familia, string $unidad) {
    if ($unidad === 'UNIDAD' && $familia === 'pan') {
        $this->fixtures['productos']['Pan']->update([
            'unidad_medida_id' => UnidadMedida::where('codigo', 'UNIDAD')->sole()->id, 'panes_por_lata' => null,
        ]);
    }
    $detalle = registroParaEliminar($this->fixtures, $familia);
    $productoAntes = $detalle->producto->toArray();
    $filtros = ['fecha_inicio' => '2026-10-18', 'fecha_fin' => '2026-10-18',
        'turno_id' => $this->fixtures['turno']->id, 'categoria' => $familia, 'page' => 1];
    $url = route('history.destroy', ['familia' => $familia, 'detalle' => $detalle->id] + $filtros);
    $this->get(route('history.index', $filtros))->assertOk()->assertSeeText('Eliminar registro')
        ->assertSee('data-eliminar-registro', false)->assertSee('value="DELETE"', false)
        ->assertSee('name="_token"', false)->assertSee('name="confirmado" value="0"', false);
    exigirCsrfHistorial();
    $this->withSession(['_token' => 'csrf-historial-ficticio'])
        ->post($url, ['_method' => 'DELETE', 'confirmado' => '1', '_token' => 'csrf-historial-ficticio'])
        ->assertRedirect(route('history.index', $filtros))->assertSessionHas('success');
    $this->assertDatabaseMissing($detalle->getTable(), ['id' => $detalle->id]);
    $this->assertDatabaseMissing('produccion', ['id' => $detalle->produccion_id]);
    $this->assertDatabaseMissing('detalle_'.$familia.'_empleado', ['detalle_'.$familia.'_id' => $detalle->id]);
    expect($detalle->producto->fresh()->toArray())->toBe($productoAntes);
    $this->assertDatabaseCount('empleados', 3);
    $this->assertDatabaseCount('usuarios_sistema', 1);
    $this->assertDatabaseCount('roles_produccion', 3);
    $this->assertDatabaseCount('turnos', 1);
    $this->get(route('history.index', $filtros))->assertOk()->assertSeeText('0 registros encontrados')
        ->assertSeeText('Registro de producción eliminado correctamente.');
})->with([['pan', 'LATA'], ['pan', 'UNIDAD'], ['torta', 'UNIDAD'], ['bocadito', 'UNIDAD']]);

test('eliminar un lote conserva otro del mismo producto y cabecera con sus participantes', function (string $familia) {
    $elegido = registroParaEliminar($this->fixtures, $familia);
    $hermano = registroParaEliminar($this->fixtures, $familia, $elegido->produccion);
    $otraCabecera = registroParaEliminar($this->fixtures, $familia);
    $antes = $hermano->fresh('empleados')->toArray();
    $this->delete(route('history.destroy', ['familia' => $familia, 'detalle' => $elegido->id]), ['confirmado' => 1])
        ->assertRedirect()->assertSessionHas('success');
    expect($hermano->fresh('empleados')->toArray())->toBe($antes)
        ->and($otraCabecera->fresh())->not->toBeNull()
        ->and($hermano->produccion->fresh())->not->toBeNull();
    $this->assertDatabaseCount('detalle_'.$familia.'_empleado', 4);
    $this->get('/history?categoria='.$familia)->assertOk()->assertSeeText('2 registros encontrados');
})->with(['pan', 'torta', 'bocadito']);

test('una cabecera legacy mixta se conserva mientras quede cualquier familia', function () {
    $pan = registroParaEliminar($this->fixtures);
    $torta = registroParaEliminar($this->fixtures, 'torta', $pan->produccion);
    $bocadito = registroParaEliminar($this->fixtures, 'bocadito', $pan->produccion);
    foreach ([$pan, $torta, $bocadito] as $indice => $detalle) {
        app(EliminarRegistroProduccion::class)->ejecutar(['pan', 'torta', 'bocadito'][$indice], $detalle->id);
        expect(Produccion::whereKey($pan->produccion_id)->exists())->toBe($indice < 2);
    }
});

test('IDs locales iguales en las tres tablas no confunden el destino', function () {
    $detalles = [];
    foreach (['pan', 'torta', 'bocadito'] as $familia) {
        $detalle = registroParaEliminar($this->fixtures, $familia);
        // Reubicar antes de volver a asociar pivotes para fabricar la colisión entre tablas.
        $detalle->empleados()->detach();
        $detalle->forceFill(['id' => 900001])->save();
        $detalle->empleados()->attach($this->fixtures['empleados'][0]->id,
            ['rol_produccion_id' => $this->fixtures['roles']['Maestro']->id]);
        $detalles[$familia] = $detalle;
    }
    $this->delete('/history/torta/900001', ['confirmado' => 1])->assertRedirect()->assertSessionHas('success');
    expect($detalles['pan']->fresh())->not->toBeNull()
        ->and($detalles['bocadito']->fresh())->not->toBeNull()
        ->and($detalles['torta']->fresh())->toBeNull();
    $this->assertDatabaseCount('detalle_pan_empleado', 1);
    $this->assertDatabaseCount('detalle_bocadito_empleado', 1);
});

test('sin confirmación aceptada no hay eliminación y se preservan los filtros', function (array $datos) {
    $detalle = registroParaEliminar($this->fixtures);
    $this->delete('/history/pan/'.$detalle->id.'?categoria=pan&fecha_inicio=2026-10-18', $datos)
        ->assertRedirect(route('history.index', ['categoria' => 'pan', 'fecha_inicio' => '2026-10-18']))
        ->assertSessionHasErrors('confirmado');
    expect($detalle->fresh())->not->toBeNull();
    $this->assertDatabaseCount('detalle_pan_empleado', 2);
})->with([[[]], [['confirmado' => 0]], [['confirmado' => 'cancelar']]]);

test('Operador no ve controles y no puede borrar aun con DELETE y CSRF válidos', function () {
    $detalle = registroParaEliminar($this->fixtures);
    $this->fixtures['rol']->update(['nombre_roles' => Rol::OPERADOR]);
    $this->get('/history')->assertOk()->assertDontSee('data-eliminar-registro', false)
        ->assertDontSeeText('Eliminar registro')->assertDontSeeText('Eliminar definitivamente');
    exigirCsrfHistorial();
    $this->withSession(['_token' => 'csrf-historial-ficticio'])
        ->delete('/history/pan/'.$detalle->id, ['confirmado' => 1, '_token' => 'csrf-historial-ficticio', 'rol' => Rol::ADMINISTRADOR])
        ->assertForbidden();
    expect($detalle->fresh())->not->toBeNull();
    $this->assertDatabaseCount('detalle_pan_empleado', 2);
});

test('invitados e inactivos no pueden eliminar', function (bool $inactivo) {
    $detalle = registroParaEliminar($this->fixtures);
    if ($inactivo) {
        $this->fixtures['usuario']->update(['activo' => false]);
    } else {
        auth()->forgetGuards();
    }
    $this->delete('/history/pan/'.$detalle->id, ['confirmado' => 1])->assertRedirect(route('login'));
    expect($detalle->fresh())->not->toBeNull();
})->with([false, true]);

test('CSRF ausente o inválido impide borrar incluso al Administrador', function (?string $token) {
    $detalle = registroParaEliminar($this->fixtures);
    exigirCsrfHistorial();
    $this->withSession(['_token' => 'csrf-historial-ficticio'])
        ->delete('/history/pan/'.$detalle->id, ['confirmado' => 1] + ($token === null ? [] : ['_token' => $token]))
        ->assertStatus(419);
    expect($detalle->fresh())->not->toBeNull();
})->with([null, 'incorrecto']);

test('GET POST y PATCH sin suplantar DELETE no borran registros', function (string $metodo) {
    $detalle = registroParaEliminar($this->fixtures);
    $this->call($metodo, '/history/pan/'.$detalle->id, ['confirmado' => 1])->assertStatus(405);
    expect($detalle->fresh())->not->toBeNull();
})->with(['GET', 'POST', 'PATCH']);

test('familia desconocida o ID inválido no seleccionan registros', function (string $destino) {
    $detalle = registroParaEliminar($this->fixtures);
    $this->delete('/history/'.$destino, ['confirmado' => 1])->assertNotFound();
    expect($detalle->fresh())->not->toBeNull();
})->with(['desconocida/1', 'pan/no-numerico']);

test('un registro inexistente muestra error y conserva filtros sin tocar otros datos', function () {
    $detalle = registroParaEliminar($this->fixtures);
    $this->delete('/history/pan/2147483647?categoria=pan', ['confirmado' => 1])
        ->assertRedirect(route('history.index', ['categoria' => 'pan']))->assertSessionHas('error');
    expect($detalle->fresh())->not->toBeNull();
});

test('un fallo SQL al borrar la cabecera revierte detalle y pivotes y muestra error', function () {
    $detalle = registroParaEliminar($this->fixtures);
    // Listener temporal: SQL real falla después del DELETE del detalle, dentro de la transacción.
    $evento = 'eloquent.deleting: '.Produccion::class;
    app('events')->listen($evento, fn () => DB::statement('DELETE FROM tabla_ficticia_inexistente_historial'));
    try {
        $this->delete('/history/pan/'.$detalle->id.'?categoria=pan', ['confirmado' => 1])
            ->assertRedirect(route('history.index', ['categoria' => 'pan']))->assertSessionHas('error');
        expect($detalle->fresh())->not->toBeNull()->and($detalle->produccion->fresh())->not->toBeNull();
        $this->assertDatabaseCount('detalle_pan_empleado', 2);
    } finally {
        app('events')->forget($evento);
    }
});

test('borrar una Torta conserva archivos que podrían estar compartidos', function () {
    $detalle = registroParaEliminar($this->fixtures, 'torta');
    Storage::disk('public')->put($detalle->foto, 'imagen ficticia');
    $this->delete('/history/torta/'.$detalle->id, ['confirmado' => 1])->assertRedirect()->assertSessionHas('success');
    Storage::disk('public')->assertExists($detalle->foto);
});
