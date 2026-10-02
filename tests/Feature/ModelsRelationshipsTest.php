<?php

use App\Models\DetalleBocadito;
use App\Models\DetalleBocaditoEmpleado;
use App\Models\DetallePan;
use App\Models\DetallePanEmpleado;
use App\Models\DetallePedido;
use App\Models\DetalleTorta;
use App\Models\DetalleTortaEmpleado;
use App\Models\Pedido;
use App\Models\Produccion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedMariaDb;

dataset('detalles de produccion', [
    'Pan' => [DetallePan::class, 'detallesPan', DetallePanEmpleado::class],
    'Torta' => [DetalleTorta::class, 'detallesTorta', DetalleTortaEmpleado::class],
    'Bocadito' => [DetalleBocadito::class, 'detallesBocadito', DetalleBocaditoEmpleado::class],
]);

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para las pruebas de persistencia aislada.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $this->fixtures = IsolatedMariaDb::fixtures();
    $this->produccion = Produccion::create([
        'fecha' => '2026-10-03', 'registrado_por_usuario_id' => $this->fixtures['usuario']->id,
    ]);
    $this->withoutVite();
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

test('una producción recupera varios detalles sin omitir productos', function ($class, $relation, $pivot) {
    $first = IsolatedMariaDb::detail($class, $this->produccion, $this->fixtures);
    $product = $first->producto->replicate();
    $product->nombre_p = 'Otro producto ficticio';
    $product->save();
    $extra = ['producto_id' => $product->id];
    if ($class !== DetalleTorta::class) {
        $extra['cantidad'] = 7;
    }
    $second = IsolatedMariaDb::detail($class, $this->produccion, $this->fixtures, $extra);
    $details = $this->produccion->fresh()->{$relation};
    expect($details)->toHaveCount(2)
        ->and($details->modelKeys())->toEqualCanonicalizing([$first->id, $second->id])
        ->and($first->produccion->is($this->produccion))->toBeTrue()
        ->and($details->pluck('producto_id')->unique())->toHaveCount(2);
    if ($class !== DetalleTorta::class) {
        expect($first->fresh()->cantidad)->toBe(3)->and($second->fresh()->cantidad)->toBe(7);
    }
})->with('detalles de produccion');

test('la observación es nullable e independiente en cada detalle', function ($class, $relation, $pivot) {
    $first = IsolatedMariaDb::detail($class, $this->produccion, $this->fixtures, ['observacion' => 'Nota de la primera pieza']);
    $second = IsolatedMariaDb::detail($class, $this->produccion, $this->fixtures, ['observacion' => 'Nota diferente']);
    $third = IsolatedMariaDb::detail($class, $this->produccion, $this->fixtures);
    $first->update(['observacion' => 'Nota actualizada']);
    expect($first->fresh()->observacion)->toBe('Nota actualizada')
        ->and($second->fresh()->observacion)->toBe('Nota diferente')
        ->and($third->fresh()->observacion)->toBeNull()
        ->and(Schema::hasColumn('produccion', 'observacion'))->toBeFalse();
})->with('detalles de produccion');

test('cada torta física conserva forma foto y observación propias sin cantidad', function () {
    $first = IsolatedMariaDb::detail(DetalleTorta::class, $this->produccion, $this->fixtures, [
        'forma' => 'circular', 'foto' => 'pruebas/primera.jpg', 'observacion' => 'Diseño floral',
    ]);
    $second = IsolatedMariaDb::detail(DetalleTorta::class, $this->produccion, $this->fixtures, [
        'forma' => 'rectangular', 'foto' => 'pruebas/segunda.jpg', 'observacion' => 'Otro diseño',
    ]);
    expect($first->producto_id)->toBe($second->producto_id)
        ->and($first->id)->not->toBe($second->id)
        ->and($this->produccion->detallesTorta()->count())->toBe(2)
        ->and($first->fresh()->only(['forma', 'foto', 'observacion']))->toBe([
            'forma' => 'circular', 'foto' => 'pruebas/primera.jpg', 'observacion' => 'Diseño floral',
        ])
        ->and($second->fresh()->forma)->toBe('rectangular')
        ->and($second->fresh()->foto)->toBe('pruebas/segunda.jpg')
        ->and($second->fresh()->observacion)->toBe('Otro diseño')
        ->and(Schema::hasColumn('detalle_torta', 'cantidad'))->toBeFalse();
});

test('participantes comparten rol y se insertan leen actualizan y eliminan por ambas claves', function ($class, $relation, $pivotClass) {
    $a = IsolatedMariaDb::detail($class, $this->produccion, $this->fixtures);
    $b = IsolatedMariaDb::detail($class, $this->produccion, $this->fixtures);
    [$worker, $other] = $this->fixtures['empleados']->all();
    $roles = $this->fixtures['roles'];
    $a->empleados()->attach($worker->id, ['rol_produccion_id' => $roles['Maestro']->id]);
    $a->empleados()->attach($other->id, ['rol_produccion_id' => $roles['Maestro']->id]);
    $b->empleados()->attach($worker->id, ['rol_produccion_id' => $roles['Maestro']->id]);
    expect($a->empleados)->toHaveCount(2)
        ->and($a->empleados->first()->pivot)->toBeInstanceOf($pivotClass)
        ->and($a->empleados->first()->pivot->rolProduccion->is($roles['Maestro']))->toBeTrue();

    DB::flushQueryLog();
    DB::enableQueryLog();
    expect($a->empleados()->updateExistingPivot($worker->id, ['rol_produccion_id' => $roles['Ayudante']->id]))->toBe(1);
    // La lectura directa del pivot también conserva ambas claves, incluso sin
    // la hidratación de belongsToMany (p. ej. consultas de RolProduccion).
    $pivot = $pivotClass::query()
        ->where($a->empleados()->getForeignPivotKeyName(), $a->id)
        ->where('empleado_id', $worker->id)->firstOrFail();
    $pivot->update(['rol_produccion_id' => $roles['Practicante']->id]);
    $pivot->refresh();
    expect($pivot->rol_produccion_id)->toBe($roles['Practicante']->id)
        ->and($pivot->empleado->is($worker))->toBeTrue()
        ->and($a->empleados()->findOrFail($other->id)->pivot->rol_produccion_id)->toBe($roles['Maestro']->id)
        ->and($b->empleados()->findOrFail($worker->id)->pivot->rol_produccion_id)->toBe($roles['Maestro']->id);
    expect($pivot->delete())->toBe(1)
        ->and($a->empleados()->detach($other->id))->toBe(1)
        ->and($a->empleados()->count())->toBe(0)
        ->and($b->empleados()->count())->toBe(1);

    $writes = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => preg_match('/^(update|delete)/i', $sql));
    expect($writes)->not->toBeEmpty();
    foreach ($writes as $sql) {
        expect($sql)->toContain('`'.$pivot->getForeignKey().'`', '`empleado_id`')->not->toMatch('/`id`/');
    }
    DB::disableQueryLog();
})->with('detalles de produccion');

test('la PK impide asignar dos roles al mismo empleado en un detalle', function ($class, $relation, $pivotClass) {
    $detail = IsolatedMariaDb::detail($class, $this->produccion, $this->fixtures);
    $worker = $this->fixtures['empleados']->first();
    $roles = $this->fixtures['roles'];
    $detail->empleados()->attach($worker->id, ['rol_produccion_id' => $roles['Maestro']->id]);
    try {
        $detail->empleados()->attach($worker->id, ['rol_produccion_id' => $roles['Ayudante']->id]);
        $this->fail('Se permitió repetir empleado en el mismo detalle.');
    } catch (QueryException $exception) {
        expect($exception->errorInfo[1])->toBe(1062);
    }
    expect($detail->empleados()->count())->toBe(1)
        ->and($detail->empleados()->first()->pivot->rol_produccion_id)->toBe($roles['Maestro']->id);
})->with('detalles de produccion');

test('Usuario Empleado Rol y Cargo mantienen sus relaciones', function () {
    $usuario = $this->fixtures['usuario']->fresh();
    expect($usuario->empleado->usuario->is($usuario))->toBeTrue()
        ->and($usuario->rol->usuarios->contains($usuario))->toBeTrue()
        ->and($usuario->empleado->cargo->empleados->contains($usuario->empleado))->toBeTrue()
        ->and($this->produccion->usuario->is($usuario))->toBeTrue()
        ->and($usuario->toArray())->not->toHaveKey('password_hash');
});

test('rehash del login escribe password_hash y conserva login logout y navegación', function () {
    $usuario = $this->fixtures['usuario'];
    $previous = $usuario->password_hash;
    config(['hashing.bcrypt.rounds' => 6]);
    Hash::forgetDrivers();
    Auth::forgetGuards();
    expect($usuario->getAuthPasswordName())->toBe('password_hash')
        ->and(Hash::needsRehash($previous))->toBeTrue();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->post('/login', ['username' => $usuario->username, 'password' => 'clave-ficticia-sprint4'])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($usuario);
    $updated = $usuario->fresh()->password_hash;
    expect($updated)->not->toBe($previous)
        ->and(Hash::check('clave-ficticia-sprint4', $updated))->toBeTrue()
        ->and(Hash::needsRehash($updated))->toBeFalse()
        ->and(Schema::hasColumn('usuarios_sistema', 'password'))->toBeFalse();
    $updates = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_starts_with($sql, 'update `usuarios_sistema`'));
    expect($updates)->toHaveCount(1);
    expect($updates->first())->toContain('`password_hash`')->not->toContain('`password`');
    DB::disableQueryLog();

    foreach (['/dashboard', '/produccion', '/history'] as $url) {
        $this->get($url)->assertOk();
    }
    $this->post('/logout')->assertRedirect('/login');
    Auth::forgetGuards();
    foreach (['/dashboard', '/produccion', '/history'] as $url) {
        $this->get($url)->assertRedirect('/login');
    }
    // La última visita como invitado conserva /history como destino intended.
    $this->post('/login', ['username' => $usuario->username, 'password' => 'clave-ficticia-sprint4'])->assertRedirect('/history');
    expect($usuario->fresh()->password_hash)->toBe($updated);
});

test('casts preservan fechas booleanos y decimales exactos sin decidir temporada', function () {
    $product = $this->fixtures['productos']['Pan'];
    $product->update(['activo' => false]);
    $unit = $this->fixtures['unidad'];
    $unit->update(['equivalencia_unidades' => '1.25']); // Valor exclusivamente ficticio.
    $pedido = Pedido::create([
        'fecha_registro' => '2026-10-03 08:10:00', 'fecha_entrega_prometida' => '2026-10-04 12:00:00',
        'entregado' => false, 'registrado_por_usuario_id' => $this->fixtures['usuario']->id,
    ]);
    $detail = DetallePedido::create([
        'pedido_id' => $pedido->id, 'producto_id' => $product->id, 'unidad_medida_id' => $unit->id,
        'cantidad' => '12345678.91',
    ]);
    expect($product->fresh()->activo)->toBeFalse()
        ->and($product->getCasts())->not->toHaveKey('temporada_fe')
        ->and($this->produccion->fresh()->fecha)->toBeInstanceOf(Carbon::class)
        ->and($this->produccion->fresh()->fecha->format('Y-m-d'))->toBe('2026-10-03')
        ->and($pedido->fresh()->entregado)->toBeFalse()
        ->and($pedido->fresh()->fecha_registro)->toBeInstanceOf(Carbon::class)
        ->and($pedido->fresh()->fecha_entrega_prometida->format('Y-m-d H:i:s'))->toBe('2026-10-04 12:00:00')
        ->and($unit->fresh()->equivalencia_unidades)->toBe('1.25')
        ->and($detail->fresh()->cantidad)->toBe('12345678.91')
        ->and($detail->fresh()->toArray()['cantidad'])->toBe('12345678.91');
});
