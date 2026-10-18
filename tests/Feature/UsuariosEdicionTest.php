<?php

use App\Actions\Usuarios\AdministrarUsuarios;
use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Empleado;
use App\Models\Produccion;
use App\Models\Rol;
use App\Models\Turno;
use App\Models\Usuario;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->admin = $this->fixtures['usuario'];
    $this->rolOperador = Rol::create(['nombre_roles' => Rol::OPERADOR]);
    $this->operador = Usuario::create([
        'username' => 'turn_mañana', 'rol_id' => $this->rolOperador->id, 'turno_id' => $this->fixtures['turno']->id,
        'empleado_id' => null, 'activo' => true, 'password_hash' => Hash::make('clave-ficticia-prueba'),
    ]);
    $this->operador->refresh();
    $this->payload = ['username' => 'operador_editado', 'rol_id' => $this->rolOperador->id,
        'turno_id' => $this->fixtures['turno']->id, 'empleado_id' => null];
    $this->url = '/usuarios/'.$this->operador->id;
    $this->actingAs($this->admin)->withoutVite();
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

test('Editar ofrece empleados libres y el actual incluso inactivo y enlaza su ficha correcta', function () {
    $actual = $this->fixtures['empleados'][0];
    $actual->update(['activo' => false]);
    $libre = $this->fixtures['empleados'][1];
    $libre->update(['activo' => false]);
    $ocupado = $this->fixtures['empleados'][2];
    $this->operador->update(['empleado_id' => $ocupado->id]);
    $antes = Usuario::orderBy('id')->get()->toArray();
    $url = '/usuarios/'.$this->admin->id.'/editar';
    $response = $this->get($url)->assertOk()->assertSeeText('Editar usuario')->assertSeeText('Sin empleado vinculado')
        ->assertSeeText('Editar empleado vinculado')->assertSee('href="'.route('empleados.edit', $actual).'"', false)
        ->assertDontSee('type="password"', false)->assertDontSee($this->admin->password_hash);
    expect($response->viewData('empleados')->modelKeys())->toEqualCanonicalizing([$actual->id, $libre->id]);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//select[@name="empleado_id"]/option[@selected]')->item(0)->getAttribute('value'))->toBe((string) $actual->id);
    $response->assertSeeText('(Inactivo)');
    $this->get(route('empleados.edit', $actual))->assertOk()->assertViewHas('empleado', fn ($empleado) => $empleado->is($actual));
    $this->get('/usuarios')->assertOk()->assertSee('href="'.url('/usuarios/'.$this->operador->id.'/editar').'"', false);
    $this->get('/personal')->assertRedirect('/usuarios');
    $this->get('/empleados')->assertOk()->assertSeeText('Usuarios')->assertSeeText('Empleados');
    expect(Usuario::orderBy('id')->get()->toArray())->toBe($antes);
});

test('Administrador edita nombre turno y vínculo por PUT o PATCH sin cambiar contraseña estado ni personal', function (string $method) {
    $turno = Turno::create(['nombre_turnos' => 'Noche']);
    $empleado = $this->fixtures['empleados'][1];
    $antes = $this->operador->fresh()->getRawOriginal();
    $personal = Empleado::orderBy('id')->get()->toArray();
    $admin = $this->admin->getRawOriginal();
    $datos = array_replace($this->payload, ['turno_id' => $turno->id, 'empleado_id' => $empleado->id,
        'id' => $this->admin->id, 'rol' => ['nombre_roles' => Rol::ADMINISTRADOR]]);
    $this->call($method, $this->url, $datos)->assertRedirect('/usuarios')->assertSessionHasNoErrors()->assertSessionHas('success', 'Usuario actualizado.');
    $actual = $this->operador->fresh();
    expect($actual->username)->toBe('operador_editado')->and($actual->turno_id)->toBe($turno->id)
        ->and($actual->empleado_id)->toBe($empleado->id)->and($actual->rol_id)->toBe($this->rolOperador->id)
        ->and($actual->password_hash)->toBe($antes['password_hash'])->and($actual->activo)->toBeTrue()
        ->and($this->admin->fresh()->getRawOriginal())->toBe($admin)
        ->and(Empleado::orderBy('id')->get()->toArray())->toBe($personal);
})->with(['PUT', 'PATCH']);

test('se puede conservar o retirar el vínculo sin eliminar al empleado ni exigir otro al editar', function () {
    $empleado = $this->fixtures['empleados'][1];
    $this->operador->update(['empleado_id' => $empleado->id]);
    $datos = $this->payload;
    unset($datos['empleado_id']);
    $this->patch($this->url, $datos)->assertSessionHasNoErrors();
    expect($this->operador->fresh()->empleado_id)->toBe($empleado->id);
    $this->patch($this->url, array_replace($datos, ['empleado_id' => '']))->assertSessionHasNoErrors();
    expect($this->operador->fresh()->empleado_id)->toBeNull()->and($empleado->fresh())->not->toBeNull()->and(Empleado::count())->toBe(3);
});

test('un vínculo propio pasa UNIQUE y otro usuario no puede apropiárselo ni mediante la Action', function () {
    $empleado = $this->fixtures['empleados'][1];
    $this->operador->update(['empleado_id' => $empleado->id]);
    $this->patch($this->url, array_replace($this->payload, ['empleado_id' => $empleado->id]))->assertSessionHasNoErrors();
    $antes = $this->admin->getRawOriginal();
    $datos = ['username' => $this->admin->username, 'rol_id' => $this->admin->rol_id, 'empleado_id' => $empleado->id];
    $this->patch('/usuarios/'.$this->admin->id, $datos)->assertSessionHasErrors(['empleado_id' => 'Este empleado ya está vinculado a otra cuenta.']);
    try {
        app(AdministrarUsuarios::class)->actualizar($this->admin->id, $datos, $this->admin->id);
        $this->fail('La Action debía rechazar el empleado ocupado.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('empleado_id');
    }
    expect($this->admin->fresh()->getRawOriginal())->toBe($antes)->and($this->operador->fresh()->empleado_id)->toBe($empleado->id);
    expect(fn () => DB::table('usuarios_sistema')->where('id', $this->admin->id)->update(['empleado_id' => $empleado->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('valida campos de edición en servidor sin modificar cuentas ni conservar credenciales', function (array $cambios, string $campo) {
    $antes = $this->operador->fresh()->getRawOriginal();
    $datos = array_replace($this->payload, $cambios) + ['extra_sensible' => 'no-conservar'];
    $this->from($this->url.'/editar')->patch($this->url, $datos)->assertRedirect($this->url.'/editar')->assertSessionHasErrors($campo)
        ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation')
        ->assertSessionMissing('_old_input.password_hash')->assertSessionMissing('_old_input.extra_sensible');
    expect($this->operador->fresh()->getRawOriginal())->toBe($antes);
})->with([
    'vacío' => [['username' => ''], 'username'],
    'demasiado largo' => [['username' => str_repeat('x', 51)], 'username'],
    'array' => [['username' => ['nombre']], 'username'],
    'espacios' => [['username' => 'nombre inválido'], 'username'],
    'mayúsculas' => [['username' => 'Nombre'], 'username'],
    'símbolos' => [['username' => 'nombre@'], 'username'],
    'rol inexistente' => [['rol_id' => 999999], 'rol_id'],
    'rol array' => [['rol_id' => [1]], 'rol_id'],
    'turno obligatorio' => [['turno_id' => null], 'turno_id'],
    'turno inexistente' => [['turno_id' => 999999], 'turno_id'],
    'turno array' => [['turno_id' => [1]], 'turno_id'],
    'empleado inexistente' => [['empleado_id' => 999999], 'empleado_id'],
    'empleado array' => [['empleado_id' => [1]], 'empleado_id'],
    'contraseña' => [['password' => 'no-conservar'], 'password'],
    'confirmación' => [['password_confirmation' => 'no-conservar'], 'password_confirmation'],
    'hash' => [['password_hash' => 'no-conservar'], 'password_hash'],
    'estado' => [['activo' => 0], 'activo'],
]);

test('edición rechaza rol ajeno al catálogo de acceso y nombre duplicado bajo collation MariaDB', function () {
    $otroRol = Rol::create(['nombre_roles' => 'Maestro']);
    $this->patch($this->url, array_replace($this->payload, ['rol_id' => $otroRol->id]))->assertSessionHasErrors('rol_id');
    $this->patch('/usuarios/'.$this->admin->id, ['username' => 'turn_manana', 'rol_id' => $this->admin->rol_id])
        ->assertSessionHasErrors('username');
    $this->patch($this->url, array_replace($this->payload, ['username' => $this->operador->username]))->assertSessionHasNoErrors();
});

test('conserva una identidad legacy exacta pero las identidades nuevas cumplen política actual', function () {
    $this->operador->update(['username' => 'Operador Heredado']);
    $this->patch($this->url, array_replace($this->payload, ['username' => 'Operador Heredado']))->assertSessionHasNoErrors();
    $this->patch($this->url, array_replace($this->payload, ['username' => 'Otro Heredado']))->assertSessionHasErrors('username');
    expect($this->operador->fresh()->username)->toBe('Operador Heredado');
});

test('desvinculación explícita y valores editados se conservan al fallar otro campo', function () {
    $this->operador->update(['empleado_id' => $this->fixtures['empleados'][1]->id]);
    $this->from($this->url.'/editar')->patch($this->url, array_replace($this->payload, ['turno_id' => null, 'empleado_id' => '']))
        ->assertSessionHasErrors('turno_id')->assertSessionHas('_old_input.username', 'operador_editado');
    $this->withCookie(config('session.cookie'), session()->getId());
    $response = $this->get($this->url.'/editar')->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//select[@name="empleado_id"]/option[@selected]')->length)->toBe(0);
    expect($xpath->query('//input[@name="username"]')->item(0)->getAttribute('value'))->toBe('operador_editado');
    expect($this->operador->fresh()->empleado_id)->not->toBeNull();
});

test('no se puede degradar al último Administrador activo y los inactivos no cuentan', function (bool $otroInactivo) {
    if ($otroInactivo) {
        Usuario::create(['username' => 'admin_inactivo', 'rol_id' => $this->admin->rol_id, 'activo' => false, 'password_hash' => Hash::make('clave-ficticia')]);
    }
    $datos = array_replace($this->payload, ['username' => $this->admin->username]);
    $antes = $this->admin->getRawOriginal();
    $this->patch('/usuarios/'.$this->admin->id, $datos)->assertSessionHasErrors(['rol_id' => 'Debe permanecer al menos un Administrador activo.']);
    expect($this->admin->fresh()->getRawOriginal())->toBe($antes);
})->with([false, true]);

test('se puede promover y degradar otra cuenta manteniendo un Administrador activo y roles laborales intactos', function () {
    $antes = Empleado::orderBy('id')->get()->toArray();
    $datos = array_replace($this->payload, ['rol_id' => $this->admin->rol_id]);
    $this->patch($this->url, $datos)->assertSessionHasNoErrors();
    expect($this->operador->fresh()->esAdministrador())->toBeTrue()->and($this->operador->fresh()->turno_id)->toBeNull();
    $this->patch($this->url, $this->payload)->assertSessionHasNoErrors();
    expect($this->operador->fresh()->esOperador())->toBeTrue()->and(Empleado::orderBy('id')->get()->toArray())->toBe($antes);
});

test('Administrador puede cambiar su propio rol si hay otro activo y vuelve al dashboard accesible', function () {
    $this->operador->update(['rol_id' => $this->admin->rol_id, 'turno_id' => null]);
    Auth::forgetGuards();
    $this->post('/login', ['username' => $this->admin->username, 'password' => 'clave-ficticia-sprint4'])->assertRedirect('/dashboard');
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->get('/usuarios')->assertOk();
    $this->patch('/usuarios/'.$this->admin->id, array_replace($this->payload, ['username' => $this->admin->username]))
        ->assertRedirect('/dashboard')->assertSessionHasNoErrors();
    expect($this->admin->fresh()->esOperador())->toBeTrue()->and($this->operador->fresh()->esAdministrador())->toBeTrue();
    Auth::forgetGuards();
    $this->get('/dashboard')->assertOk();
    $this->get('/usuarios')->assertForbidden();
});

test('Action revalida al actor desde la base de datos aunque su modelo de sesión esté obsoleto', function (string $cambio) {
    $this->admin->load('rol');
    DB::table('usuarios_sistema')->where('id', $this->admin->id)->update($cambio === 'inactivo' ? ['activo' => false] : ['rol_id' => $this->rolOperador->id]);
    $antes = $this->operador->fresh()->getRawOriginal();
    try {
        app(AdministrarUsuarios::class)->actualizar($this->operador->id, $this->payload, $this->admin->id);
        $this->fail('La Action debía rechazar al actor sin autorización actual.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
    expect($this->operador->fresh()->getRawOriginal())->toBe($antes);
})->with(['inactivo', 'Operador']);

test('GET no edita y el endpoint de escritura exige CSRF además del permiso', function () {
    $antes = $this->operador->fresh()->getRawOriginal();
    $this->get($this->url.'?username=inyectado')->assertStatus(405);
    $middleware = Mockery::mock(PreventRequestForgery::class, [app(), app('encrypter')])->makePartial()->shouldAllowMockingProtectedMethods();
    $middleware->shouldReceive('runningUnitTests')->andReturn(false);
    app()->instance(PreventRequestForgery::class, $middleware);
    $this->patch($this->url, $this->payload)->assertStatus(419);
    $this->withSession(['_token' => 'token-ficticio']);
    $this->actingAs($this->operador)->patch($this->url, $this->payload + ['_token' => 'token-ficticio'])->assertForbidden();
    $this->flushSession();
    $this->withSession(['_token' => 'token-ficticio']);
    $this->actingAs($this->admin)->patch($this->url, $this->payload + ['_token' => 'token-ficticio'])->assertRedirect('/usuarios')->assertSessionHasNoErrors();
    expect($this->operador->fresh()->password_hash)->toBe($antes['password_hash']);
});

test('Operador no accede a edición tampoco en JSON', function (string $method) {
    $this->actingAs($this->operador);
    $this->json($method, $this->url.($method === 'GET' ? '/editar' : ''), $this->payload)->assertForbidden();
    expect($this->operador->fresh()->username)->toBe('turn_mañana');
})->with(['GET', 'PATCH', 'PUT']);

test('cuentas compartidas conservan nombre turno estado y credencial al vincular o desvincular', function (string $nombre, bool $activo) {
    $turno = $nombre === 'turn_noche' ? Turno::create(['nombre_turnos' => 'Noche']) : $this->fixtures['turno'];
    $this->operador->update(['username' => $nombre, 'turno_id' => $turno->id, 'activo' => $activo]);
    $antes = $this->operador->fresh()->getRawOriginal();
    foreach ([$this->fixtures['empleados'][1]->id, null] as $empleadoId) {
        $this->patch($this->url, ['username' => $nombre, 'rol_id' => $this->rolOperador->id, 'turno_id' => $turno->id, 'empleado_id' => $empleadoId])
            ->assertSessionHasNoErrors();
        $actual = $this->operador->fresh();
        foreach (['username', 'turno_id', 'rol_id', 'activo', 'password_hash'] as $campo) {
            expect($actual->getRawOriginal($campo))->toBe($antes[$campo]);
        }
        expect($actual->empleado_id)->toBe($empleadoId);
    }
})->with(['turn_mañana', 'turn_noche'])->with([true, false]);

test('vincular cambiar acceso y desvincular conservan todas las participaciones históricas y roles laborales', function () {
    $cabecera = Produccion::create(['fecha' => '2026-10-18', 'registrado_por_usuario_id' => $this->operador->id, 'categoria_id' => $this->fixtures['productos']['Pan']->categoria_id, 'turno_id' => $this->fixtures['turno']->id]);
    foreach ([DetallePan::class, DetalleTorta::class, DetalleBocadito::class] as $clase) {
        $detalle = IsolatedMariaDb::detail($clase, $cabecera, $this->fixtures);
        $detalle->empleados()->attach($this->fixtures['empleados'][1]->id, ['rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id]);
    }
    $tablas = ['produccion', 'detalle_pan', 'detalle_torta', 'detalle_bocadito', 'detalle_pan_empleado', 'detalle_torta_empleado', 'detalle_bocadito_empleado', 'empleados'];
    $antes = collect($tablas)->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->get()->toArray()])->all();
    foreach ([[$this->fixtures['empleados'][1]->id, $this->admin->rol_id], [null, $this->rolOperador->id]] as [$empleadoId, $rolId]) {
        $this->patch($this->url, array_replace($this->payload, ['empleado_id' => $empleadoId, 'rol_id' => $rolId]))->assertSessionHasNoErrors();
        foreach ($antes as $tabla => $filas) {
            expect(DB::table($tabla)->get()->toArray())->toEqual($filas);
        }
    }
    $this->get('/history?fecha_inicio=2026-10-18&fecha_fin=2026-10-18')->assertOk()->assertSeeText('Ayudantes: '.$this->fixtures['empleados'][1]->nombre_empleados);
});

test('colisión UNIQUE después de validar muestra error comprensible y revierte la transacción', function () {
    $empleado = $this->fixtures['empleados'][1];
    $antes = $this->operador->fresh()->getRawOriginal();
    $otroAntes = $this->admin->fresh()->getRawOriginal();
    $intervenir = true;
    DB::connection()->beforeExecuting(function ($sql) use (&$intervenir, $empleado) {
        if ($intervenir && str_starts_with($sql, 'update `usuarios_sistema`')) {
            $intervenir = false;
            // Simular la colisión final; esta escritura pertenece a la misma transacción ficticia.
            DB::table('usuarios_sistema')->where('id', $this->admin->id)->update(['empleado_id' => $empleado->id]);
        }
    });
    $this->from($this->url.'/editar')->patch($this->url, array_replace($this->payload, ['empleado_id' => $empleado->id]))
        ->assertRedirect($this->url.'/editar')->assertSessionHasErrors(['empleado_id' => 'Este empleado ya está vinculado a otra cuenta.']);
    expect($intervenir)->toBeFalse()->and($this->operador->fresh()->getRawOriginal())->toBe($antes)
        ->and($this->admin->fresh()->getRawOriginal())->toBe($otroAntes);
});
