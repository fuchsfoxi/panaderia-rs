<?php

use App\Actions\Usuarios\AdministrarUsuarios;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
    $this->claveOperador = Str::random(32);
    $this->operador = Usuario::create([
        'username' => 'turn_manana', 'rol_id' => $this->rolOperador->id, 'empleado_id' => null,
        'turno_id' => $this->fixtures['turno']->id, 'activo' => true, 'password_hash' => Hash::make($this->claveOperador),
    ]);
    $clave = Str::random(32);
    $this->payload = ['username' => 'turn_nuevo', 'rol_id' => $this->rolOperador->id, 'turno_id' => $this->fixtures['turno']->id,
        'password' => $clave, 'password_confirmation' => $clave];
    $this->withoutVite();
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

dataset('endpoints usuarios', [
    ['GET', '/usuarios'], ['GET', '/usuarios/crear'], ['POST', '/usuarios'],
    ['PATCH', '/usuarios/%d/estado'], ['GET', '/usuarios/%d/password'], ['PATCH', '/usuarios/%d/password'],
    ['GET', '/usuarios/%d/editar'], ['PATCH', '/usuarios/%d'], ['PUT', '/usuarios/%d'],
]);

test('invitado y Operador no acceden a ningún endpoint de Usuarios', function (bool $guest, string $metodo, string $ruta) {
    if (! $guest) {
        $this->actingAs($this->operador);
    }
    $response = $this->call($metodo, sprintf($ruta, $this->operador->id), $this->payload);
    if ($guest) {
        $response->assertRedirect('/login');
    } else {
        $response->assertForbidden();
    }
    expect(Usuario::count())->toBe(2)->and($this->operador->fresh()->activo)->toBeTrue();
})->with([true, false])->with('endpoints usuarios');

test('Admin ve listado formularios y sidebar sin exponer credenciales ni inventar Empleado', function () {
    $this->actingAs($this->admin);
    foreach (['/usuarios', '/usuarios/crear', '/usuarios/'.$this->operador->id.'/password'] as $url) {
        $response = $this->get($url)->assertOk()->assertSeeText('Usuarios')
            ->assertDontSee($this->claveOperador)->assertDontSee($this->operador->password_hash);
        if ($url === '/usuarios') {
            $response->assertSeeText('turn_manana')->assertSeeText('Cuenta compartida')->assertSeeText('Mañana')
                ->assertSeeText('Activo')->assertSeeText('Cambiar contraseña')->assertSeeText('Desactivar');
        } else {
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new DOMXPath($document);
            foreach ($xpath->query('//input[@type="password"]') as $input) {
                expect($input->getAttribute('value'))->toBe('');
            }
        }
    }
    $this->flushSession();
    $this->actingAs($this->operador)->get('/dashboard')->assertOk()->assertDontSeeText('Productos')->assertDontSeeText('Usuarios');
});

test('Admin crea un Operador activo con turno real hash y sin Empleado', function () {
    $adminAntes = $this->admin->getRawOriginal();
    $this->actingAs($this->admin)->post('/usuarios', $this->payload)->assertRedirect('/usuarios')->assertSessionHasNoErrors();
    $usuario = Usuario::where('username', $this->payload['username'])->sole();
    expect($usuario->empleado_id)->toBeNull()->and($usuario->turno->is($this->fixtures['turno']))->toBeTrue()
        ->and($usuario->activo)->toBeTrue()->and($usuario->password_hash)->not->toBe($this->payload['password'])
        ->and(Hash::check($this->payload['password'], $usuario->password_hash))->toBeTrue()
        ->and($usuario->toArray())->not->toHaveKey('password_hash')
        ->and($this->admin->fresh()->getRawOriginal())->toBe($adminAntes);
    $this->get('/usuarios')->assertOk()->assertDontSee($this->payload['password'])->assertDontSee($usuario->password_hash);
});

test('Admin nuevo se crea sin turno aunque se envíe uno válido', function () {
    $this->payload['rol_id'] = $this->admin->rol_id;
    $this->actingAs($this->admin)->post('/usuarios', $this->payload)->assertRedirect('/usuarios')->assertSessionHasNoErrors();
    $usuario = Usuario::where('username', $this->payload['username'])->sole();
    expect($usuario->turno_id)->toBeNull()->and($usuario->empleado_id)->toBeNull()->and($usuario->esAdministrador())->toBeTrue();
});

test('Administrador no exige turno al crearse', function () {
    $this->payload['rol_id'] = $this->admin->rol_id;
    unset($this->payload['turno_id']);
    $this->actingAs($this->admin)->post('/usuarios', $this->payload)->assertRedirect('/usuarios')->assertSessionHasNoErrors();
});

test('crear usuario rechaza entrada inválida sin conservar contraseñas ni campos ajenos', function (array $cambios, string $campo) {
    $datos = array_replace($this->payload, $cambios) + ['extra_sensible' => 'no-persistir'];
    $this->actingAs($this->admin)->from('/usuarios/crear')->post('/usuarios', $datos)
        ->assertRedirect('/usuarios/crear')->assertSessionHasErrors($campo)
        ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation')
        ->assertSessionMissing('_old_input.password_hash')->assertSessionMissing('_old_input.extra_sensible');
    expect(Usuario::count())->toBe(2);
})->with([
    'duplicado' => [['username' => 'turn_manana'], 'username'],
    'vacío' => [['username' => ''], 'username'],
    'largo' => [['username' => str_repeat('x', 51)], 'username'],
    'mayúsculas' => [['username' => 'Turn_manana'], 'username'],
    'espacios' => [['username' => 'turn manana'], 'username'],
    'duplicado equivalente con ñ' => [['username' => 'turn_mañana'], 'username'],
    'símbolos no permitidos' => [['username' => 'usuario@@'], 'username'],
    'array usuario' => [['username' => ['dato']], 'username'],
    'turno obligatorio' => [['turno_id' => null], 'turno_id'],
    'turno inválido' => [['turno_id' => 999999], 'turno_id'],
    'array turno' => [['turno_id' => ['dato']], 'turno_id'],
    'rol inválido' => [['rol_id' => 999999], 'rol_id'],
    'array rol' => [['rol_id' => ['dato']], 'rol_id'],
    'sin contraseña' => [['password' => ''], 'password'],
    'corta' => [['password' => 'corta'], 'password'],
    'larga' => [['password' => str_repeat('x', 73)], 'password'],
    'bytes unicode' => [['password' => str_repeat('á', 37)], 'password'],
    'array contraseña' => [['password' => ['dato']], 'password'],
    'sin confirmación' => [['password_confirmation' => null], 'password'],
    'hash inyectado' => [['password_hash' => 'no-persistir'], 'password_hash'],
    'empleado inyectado' => [['empleado_id' => 99], 'empleado_id'],
    'estado inyectado' => [['activo' => false], 'activo'],
]);

test('un rol heredado no autorizado no puede asignarse a nuevas cuentas', function () {
    $heredado = Rol::create(['nombre_roles' => 'Encargado']);
    $this->payload['rol_id'] = $heredado->id;
    $this->actingAs($this->admin)->post('/usuarios', $this->payload)->assertSessionHasErrors('rol_id');
});

test('activar y desactivar es explícito e idempotente y conserva credencial y relaciones', function () {
    $antes = $this->operador->getRawOriginal();
    $this->actingAs($this->admin);
    foreach ([false, false, true, true] as $activo) {
        $this->patch('/usuarios/'.$this->operador->id.'/estado', ['activo' => $activo ? 1 : 0])->assertRedirect('/usuarios')->assertSessionHasNoErrors();
        $usuario = $this->operador->fresh();
        expect($usuario->activo)->toBe($activo)->and($usuario->password_hash)->toBe($antes['password_hash'])
            ->and($usuario->empleado_id)->toBeNull()->and($usuario->turno_id)->toBe($antes['turno_id']);
    }
});

test('no se permite desactivar el último Administrador activo por ninguna identidad fija', function () {
    $this->actingAs($this->admin)->patch('/usuarios/'.$this->admin->id.'/estado', ['activo' => 0])
        ->assertSessionHasErrors('activo');
    expect($this->admin->fresh()->activo)->toBeTrue();
    $otro = Usuario::create(['username' => 'admin_adicional', 'rol_id' => $this->admin->rol_id, 'empleado_id' => null,
        'password_hash' => Hash::make(Str::random(32)), 'activo' => true]);
    $this->patch('/usuarios/'.$otro->id.'/estado', ['activo' => 0])->assertRedirect('/usuarios')->assertSessionHasNoErrors();
    $this->patch('/usuarios/'.$this->admin->id.'/estado', ['activo' => 0])->assertSessionHasErrors('activo');
    expect($this->admin->fresh()->activo)->toBeTrue()->and($otro->fresh()->activo)->toBeFalse();
});

test('cuenta inactiva falla con mensaje genérico conserva username y consume límite', function () {
    $this->operador->update(['activo' => false]);
    for ($i = 0; $i < 5; $i++) {
        $this->from('/login')->post('/login', ['username' => $this->operador->username, 'password' => $this->claveOperador])
            ->assertRedirect('/login')->assertSessionHasErrors(['username' => 'Las credenciales ingresadas no son correctas.'])
            ->assertSessionHas('_old_input', ['username' => $this->operador->username]);
        $this->assertGuest();
    }
    $this->from('/login')->post('/login', ['username' => $this->operador->username, 'password' => $this->claveOperador])
        ->assertSessionHasErrors('username');
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->get('/login')->assertOk()->assertSee('data-login-retry-after=', false)->assertDontSeeText('desactivada');
});

test('desactivación revoca una sesión abierta en la siguiente petición protegida', function (string $driver, string $ruta) {
    config(['session.driver' => $driver, 'session.connection' => 'sprint4_test']);
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    Auth::forgetGuards();
    $this->post('/login', ['username' => $this->operador->username, 'password' => $this->claveOperador])->assertRedirect('/dashboard');
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->get('/dashboard')->assertOk();
    $this->withSession(['dato_privado' => 'ficticio']);
    $id = session()->getId();
    $token = session()->token();
    app(AdministrarUsuarios::class)->estado($this->operador->id, false, $this->admin->id);
    Auth::forgetGuards();
    $this->get($ruta)->assertRedirect('/login')->assertSessionMissing('dato_privado');
    $this->assertGuest();
    expect(session()->getId())->not->toBe($id)->and(session()->token())->not->toBe($token);
})->with(['array', 'database'])->with(['/dashboard', '/produccion', '/history']);

test('una cuenta reactivada puede autenticar sin cambiar su contraseña', function () {
    app(AdministrarUsuarios::class)->estado($this->operador->id, false, $this->admin->id);
    app(AdministrarUsuarios::class)->estado($this->operador->id, true, $this->admin->id);
    $this->post('/login', ['username' => $this->operador->username, 'password' => $this->claveOperador])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($this->operador);
});

test('cambiar contraseña actualiza solo la cuenta indicada y nueva autentica antigua no', function () {
    $nueva = Str::random(32);
    $hashAdmin = $this->admin->password_hash;
    $this->actingAs($this->admin)->patch('/usuarios/'.$this->operador->id.'/password', ['password' => $nueva, 'password_confirmation' => $nueva])
        ->assertRedirect('/usuarios')->assertSessionHasNoErrors();
    expect(Hash::check($nueva, $this->operador->fresh()->password_hash))->toBeTrue()
        ->and($this->admin->fresh()->password_hash)->toBe($hashAdmin);
    $this->post('/logout');
    Auth::forgetGuards();
    $this->from('/login')->post('/login', ['username' => $this->operador->username, 'password' => $this->claveOperador])->assertSessionHasErrors('username');
    $this->assertGuest();
    $this->post('/login', ['username' => $this->operador->username, 'password' => $nueva])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($this->operador);
});

test('cambio de contraseña revoca la sesión inicializada solo de esa cuenta', function (string $driver) {
    config(['session.driver' => $driver, 'session.connection' => 'sprint4_test']);
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    Auth::forgetGuards();
    $this->post('/login', ['username' => $this->operador->username, 'password' => $this->claveOperador]);
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->get('/dashboard')->assertOk()->assertSessionHas('password_hash_web');
    app(AdministrarUsuarios::class)->password($this->operador->id, Str::random(32), $this->admin->id);
    Auth::forgetGuards();
    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
    $this->flushSession();
    $this->actingAs($this->admin)->get('/usuarios')->assertOk();
})->with(['array', 'database']);

test('cambiar contraseña exige confirmación y no rellena campos en el siguiente HTML', function () {
    $nueva = Str::random(32);
    $url = '/usuarios/'.$this->operador->id.'/password';
    $antes = $this->operador->password_hash;
    $this->actingAs($this->admin)->from($url)->patch($url, ['password' => $nueva, 'password_confirmation' => 'distinta-ficticia'])
        ->assertRedirect($url)->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password');
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->get($url)->assertOk()->assertDontSee($nueva)->assertDontSee('distinta-ficticia');
    expect($this->operador->fresh()->password_hash)->toBe($antes);
});

test('no existe DELETE de usuarios', function () {
    $this->actingAs($this->admin)->delete('/usuarios/'.$this->operador->id)->assertStatus(405);
    expect(Usuario::count())->toBe(2);
});
