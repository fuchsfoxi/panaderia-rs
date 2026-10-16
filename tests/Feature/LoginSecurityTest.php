<?php

use App\Models\Cargo;
use App\Models\Empleado;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para probar login en MariaDB aislada.');
    }

    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $cargo = Cargo::create(['nombre_cargos' => 'Cargo de prueba de login']);
    $empleado = Empleado::create(['nombre_empleados' => 'Empleado ficticio de login', 'cargo_id' => $cargo->id]);
    $rol = Rol::create(['nombre_roles' => Rol::ADMINISTRADOR]);
    $this->usuario = Usuario::create([
        'username' => 'login.ficticio',
        'password_hash' => Hash::make('clave-exclusiva-de-pruebas'),
        'empleado_id' => $empleado->id,
        'rol_id' => $rol->id,
    ]);
    $this->withoutVite();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

test('guest ve login con branding coherente y sin recuperación inexistente', function () {
    $this->get('/login')->assertOk()->assertSeeText('Panadería RS')
        ->assertDontSeeText('Panificadora Amazónica')->assertDontSeeText('PANIFICADORA AMAZÓNICA')
        ->assertDontSeeText('¿Olvidaste tu contraseña?')->assertSee('name="_token"', false);
});

test('el autenticado no vuelve a GET ni POST login', function () {
    $this->actingAs($this->usuario)->get('/login')->assertRedirect('/dashboard');
    $this->post('/login')->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($this->usuario);
});

test('credenciales válidas autentican en web y regeneran la sesión', function () {
    $this->get('/login');
    $id = session()->getId();
    $this->post('/login', ['username' => $this->usuario->username, 'password' => 'clave-exclusiva-de-pruebas'])
        ->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($this->usuario, 'web');
    expect(session()->getId())->not->toBe($id)
        ->and($this->usuario->getTable())->toBe('usuarios_sistema')
        ->and($this->usuario->getAuthPasswordName())->toBe('password_hash');
});

test('fallo genérico visible conserva solo username y nunca contraseña', function (string $username) {
    $this->from('/login')->post('/login', [
        'username' => $username, 'password' => 'incorrecta-ficticia', 'dato_extra' => 'no-persistir',
    ])->assertRedirect('/login')->assertSessionHasErrors([
        'username' => 'Las credenciales ingresadas no son correctas.',
    ])->assertSessionHas('_old_input', ['username' => $username]);
    $this->assertGuest();
    // Transportar la cookie como un navegador: la sesión JSON se relee del handler.
    $this->withCookie(config('session.cookie'), session()->getId());
    $response = $this->get('/login')->assertOk()
        ->assertSeeText('Las credenciales ingresadas no son correctas.')
        ->assertDontSee('incorrecta-ficticia')->assertDontSee('no-persistir');
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//input[@name="username"]')->item(0)->getAttribute('value'))->toBe($username)
        ->and($xpath->query('//input[@name="password"]')->item(0)->getAttribute('value'))->toBe('')
        ->and($xpath->query('//*[@id="login-errors"]')->length)->toBe(0)
        ->and(trim($xpath->query('//*[@id="login-credentials"]')->item(0)?->textContent ?? ''))
        ->toBe('Las credenciales ingresadas no son correctas.')
        ->and($xpath->query('//*[@id="login-credentials"]/following-sibling::input[@type="submit"]')->length)->toBe(1)
        ->and($xpath->query('//input[@name="password"]')->item(0)->getAttribute('aria-describedby'))->toBe('login-credentials');
})->with(['login.ficticio', 'no-existe.ficticio']);

test('validación rechaza entradas inválidas con feedback visible y sin persistir payload sensible', function (array $payload, string $campo, string $mensaje) {
    $payload += ['username' => 'login.ficticio', 'password' => 'clave-exclusiva-de-pruebas'];
    $this->from('/login')->post('/login', $payload + ['dato_extra' => 'no-persistir'])
        ->assertRedirect('/login')->assertSessionHasErrors([$campo => $mensaje])
        ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.dato_extra');
    $this->assertGuest();
    $this->withCookie(config('session.cookie'), session()->getId());
    $response = $this->get('/login')->assertOk()->assertSeeText($mensaje)->assertDontSee('no-persistir');
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $input = $xpath->query('//input[@name="'.$campo.'"]')->item(0);
    expect($input->getAttribute('aria-invalid'))->toBe('true')
        ->and($input->getAttribute('aria-describedby'))->toBe($campo.'-error')
        ->and(trim($xpath->query('//input[@name="'.$campo.'"]/parent::div/following-sibling::p[@id="'.$campo.'-error"]')->item(0)?->textContent ?? ''))
        ->toBe($mensaje)
        ->and($xpath->query('//*[@id="login-errors"]')->length)->toBe(0);
})->with([
    'usuario vacío' => [['username' => ''], 'username', 'Ingresa tu usuario.'],
    'contraseña vacía' => [['password' => ''], 'password', 'Ingresa tu contraseña.'],
    'usuario largo' => [['username' => str_repeat('u', 51)], 'username', 'El usuario no debe superar los 50 caracteres.'],
    'usuario array' => [['username' => ['dato' => 'no-persistir']], 'username', 'El usuario debe ser texto.'],
    'contraseña array' => [['password' => ['dato' => 'no-persistir']], 'password', 'La contraseña debe ser texto.'],
    'contraseña larga' => [['password' => str_repeat('p', 1025)], 'password', 'La contraseña no debe superar los 1024 caracteres.'],
]);

test('JSON malformado por tipo se rechaza como validación sin autenticar', function (mixed $password) {
    $this->postJson('/login', ['username' => 'login.ficticio', 'password' => $password])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->assertGuest();
})->with(['entero' => 123456, 'booleano' => true, 'array' => [['dato' => 'ficticio']]]);

test('username de 50 caracteres y contraseña corta existente siguen siendo válidos', function () {
    $this->usuario->update(['username' => str_repeat('u', 50), 'password_hash' => Hash::make('corta')]);
    $this->post('/login', ['username' => $this->usuario->username, 'password' => 'corta'])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($this->usuario);
});

test('cinco fallos limitan el sexto intento incluso válido y muestran segundos de espera', function (bool $databaseCache) {
    if ($databaseCache) {
        // Verificar también el almacén persistente predeterminado, dentro de la BD desechable.
        RateLimiter::swap(new CacheRateLimiter(Cache::store('database')));
    }
    for ($i = 0; $i < 5; $i++) {
        $this->from('/login')->post('/login', ['username' => 'login.ficticio', 'password' => 'incorrecta-ficticia'])
            ->assertRedirect('/login')->assertSessionHasErrors(['username' => 'Las credenciales ingresadas no son correctas.']);
    }
    $this->from('/login')->post('/login', ['username' => 'login.ficticio', 'password' => 'clave-exclusiva-de-pruebas'])
        ->assertRedirect('/login')->assertSessionHasErrors('username')
        ->assertSessionHas('_old_input', ['username' => 'login.ficticio']);
    $this->assertGuest();
    $this->withCookie(config('session.cookie'), session()->getId());
    $html = $this->get('/login')->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $wait = $xpath->query('//*[@id="login-wait"]')->item(0);
    expect($wait)->not->toBeNull();
    $seconds = $wait->getAttribute('data-login-retry-after');
    expect($seconds)->toMatch('/^\d+$/')
        ->and((int) $seconds)->toBeGreaterThan(0)->toBeLessThanOrEqual(60)
        ->and(trim($wait->textContent))->toBe('Demasiados intentos. Inténtalo de nuevo en '.$seconds.' s.')
        ->and($xpath->query('//*[@data-login-seconds]')->item(0)->textContent)->toBe($seconds)
        ->and($xpath->query('//input[@type="submit" and @disabled and @aria-describedby="login-wait"]')->length)->toBe(1)
        ->and($xpath->query('//input[(@name="username" or @name="password") and @disabled]')->length)->toBe(0);

    // Quitar disabled o adelantar un contador en el cliente no altera el límite real.
    $this->from('/login')->post('/login', [
        'username' => 'login.ficticio', 'password' => 'clave-exclusiva-de-pruebas',
        'login_retry_at' => 0, 'login_retry_after' => 0,
    ])->assertRedirect('/login')->assertSessionHasErrors('username');
    $this->assertGuest();
    expect(session('errors')->first('username'))->toStartWith('Demasiados intentos.');
    $this->travel(61)->seconds();
    $this->post('/login', ['username' => 'login.ficticio', 'password' => 'clave-exclusiva-de-pruebas'])
        ->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($this->usuario);
})->with(['cache array' => false, 'cache database aislada' => true]);

test('el plazo de espera enviado a Blade descuenta el tiempo de la redirección', function () {
    $this->freezeTime();
    for ($i = 0; $i < 6; $i++) {
        $this->from('/login')->post('/login', ['username' => 'login.ficticio', 'password' => 'incorrecta-ficticia'])
            ->assertRedirect('/login');
    }
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->travel(22)->seconds();
    $this->get('/login')->assertOk()->assertSee('data-login-retry-after="38"', false);
});

test('los mensajes por campo del backend se escapan en Blade', function () {
    $errors = (new ViewErrorBag)->put('default', new MessageBag([
        'username' => '<script>ejemplo-no-ejecutable</script>',
        'password' => '<b>mensaje de prueba</b>',
    ]));
    $html = view('auth.login', ['errors' => $errors])->render();
    expect($html)->toContain('&lt;script&gt;ejemplo-no-ejecutable&lt;/script&gt;', '&lt;b&gt;mensaje de prueba&lt;/b&gt;')
        ->not->toContain('<script>ejemplo-no-ejecutable</script>', '<b>mensaje de prueba</b>');
});

test('límite normaliza mayúsculas acentos y espacios sin cambiar la identidad autenticada', function () {
    foreach (['login.ficticio', 'LOGIN.FICTICIO', 'lógin.ficticio', ' login.ficticio ', 'Login.Ficticio'] as $username) {
        $this->from('/login')->post('/login', ['username' => $username, 'password' => 'incorrecta-ficticia']);
    }
    $this->from('/login')->post('/login', ['username' => 'login.ficticio', 'password' => 'clave-exclusiva-de-pruebas'])
        ->assertSessionHasErrors('username');
    $this->assertGuest();
    expect(session('errors')->first('username'))->toStartWith('Demasiados intentos.');
});

test('límite no bloquea otros usuarios de la misma IP ni otras IP', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->from('/login')->post('/login', ['username' => 'otro.ficticio', 'password' => 'incorrecta-ficticia']);
    }
    $this->post('/login', ['username' => 'login.ficticio', 'password' => 'clave-exclusiva-de-pruebas'])->assertRedirect('/dashboard');
    $this->post('/logout');
    Auth::forgetGuards();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11']);
    $this->from('/login')->post('/login', ['username' => 'otro.ficticio', 'password' => 'incorrecta-ficticia'])
        ->assertSessionHasErrors(['username' => 'Las credenciales ingresadas no son correctas.']);
});

test('login exitoso limpia solo su contador y permite cinco fallos nuevos', function () {
    for ($i = 0; $i < 4; $i++) {
        $this->from('/login')->post('/login', ['username' => 'login.ficticio', 'password' => 'incorrecta-ficticia']);
    }
    for ($i = 0; $i < 5; $i++) {
        $this->from('/login')->post('/login', ['username' => 'otro.ficticio', 'password' => 'incorrecta-ficticia']);
    }
    $this->post('/login', ['username' => 'login.ficticio', 'password' => 'clave-exclusiva-de-pruebas'])->assertRedirect('/dashboard');
    $this->post('/logout');
    Auth::forgetGuards();
    for ($i = 0; $i < 5; $i++) {
        $this->from('/login')->post('/login', ['username' => 'login.ficticio', 'password' => 'incorrecta-ficticia'])
            ->assertSessionHasErrors(['username' => 'Las credenciales ingresadas no son correctas.']);
    }
    $this->from('/login')->post('/login', ['username' => 'otro.ficticio', 'password' => 'incorrecta-ficticia'])
        ->assertSessionHasErrors('username');
    expect(session('errors')->first('username'))->toStartWith('Demasiados intentos.');
});

test('logout invalida datos ID y token de sesión y vuelve a proteger rutas', function () {
    $this->actingAs($this->usuario)->withSession(['dato_privado' => 'ficticio', '_token' => 'csrf-ficticio']);
    $id = session()->getId();
    $token = session()->token();
    $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('dato_privado');
    $this->assertGuest();
    expect(session()->getId())->not->toBe($id)->and(session()->token())->not->toBe($token);
    Auth::forgetGuards();
    foreach (['/dashboard', '/productos', '/produccion', '/history'] as $ruta) {
        $this->get($ruta)->assertRedirect('/login');
    }
});

test('POST login exige CSRF real y no autentica sin token válido', function (?string $token, int $status) {
    $middleware = Mockery::mock(PreventRequestForgery::class, [app(), app('encrypter')])
        ->makePartial()->shouldAllowMockingProtectedMethods();
    $middleware->shouldReceive('runningUnitTests')->andReturnFalse();
    app()->instance(PreventRequestForgery::class, $middleware);
    $payload = ['username' => 'login.ficticio', 'password' => 'clave-exclusiva-de-pruebas'];
    if ($token !== null) {
        $payload['_token'] = $token;
    }
    $this->withSession(['_token' => 'csrf-ficticio'])->post('/login', $payload)->assertStatus($status);
    if ($status === 302) {
        $this->assertAuthenticatedAs($this->usuario);
    } else {
        $this->assertGuest();
    }
})->with(['ausente' => [null, 419], 'incorrecto' => ['no-valido', 419], 'válido' => ['csrf-ficticio', 302]]);

test('env example deja vacías las variables de credenciales y aprovisionamiento', function () {
    $example = file_get_contents(base_path('.env.example'));
    expect($example)->toMatch('/^INITIAL_ADMIN_PASSWORD=$/m');
    foreach (preg_split('/\R/', $example) as $line) {
        if (preg_match('/^#?\s*[A-Z_]*(PASSWORD|SECRET|TOKEN|APP_KEY|ACCESS_KEY_ID)[A-Z_]*=(.*)$/', $line, $match)) {
            expect(trim($match[2]))->toBeIn(['', 'null']);
        }
    }
});
