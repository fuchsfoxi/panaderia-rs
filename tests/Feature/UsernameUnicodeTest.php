<?php

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->withoutVite();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.25']);
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

test('MariaDB unicode_ci considera iguales turn_manana y turn_mañana y UNIQUE rechaza la segunda', function () {
    $column = DB::selectOne("SELECT COLLATION_NAME AS collation FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_sistema' AND COLUMN_NAME = 'username'");
    expect($column->collation)->toBe('utf8mb4_unicode_ci');
    $this->fixtures['usuario']->update(['username' => 'turn_manana']);
    $attributes = $this->fixtures['usuario']->only('rol_id', 'password_hash');
    try {
        Usuario::create($attributes + ['username' => 'turn_mañana', 'empleado_id' => null]);
        $this->fail('UNIQUE debía rechazar la segunda identidad equivalente.');
    } catch (QueryException $exception) {
        expect($exception->errorInfo[0])->toBe('23000')
            ->and($exception->errorInfo[1])->toBe(1062);
    }
    expect(Usuario::where('username', 'turn_mañana')->sole()->id)->toBe($this->fixtures['usuario']->id)
        ->and(Usuario::where('username', 'TURN_MAÑANA')->sole()->id)->toBe($this->fixtures['usuario']->id)
        ->and(Usuario::count())->toBe(1);
    fwrite(STDOUT, "\nMariaDB aislada: username utf8mb4_unicode_ci; turn_manana = turn_mañana = TURN_MAÑANA; segundo INSERT rechazado con SQLSTATE 23000 / error 1062.\n");
});

test('crear acepta usernames con ñ y los demás caracteres permitidos', function (string $username) {
    $password = 'Mañana#2026';
    $this->actingAs($this->fixtures['usuario'])->post('/usuarios', [
        'username' => $username, 'rol_id' => $this->fixtures['usuario']->rol_id,
        'password' => $password, 'password_confirmation' => $password,
    ])->assertRedirect('/usuarios')->assertSessionHasNoErrors();
    $usuario = Usuario::where('username', $username)->sole();
    expect($usuario->username)->toBe($username)
        ->and(Hash::check($password, $usuario->password_hash))->toBeTrue();
    Auth::forgetGuards();
    $this->flushSession();
    $this->post('/login', ['username' => $username, 'password' => $password])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($usuario);
})->with(['turn_mañana', 'señor_pan', 'turn_noche', 'operador01', 'cuenta.ñ-01_a']);

test('crear rechaza espacios símbolos ajenos y mayúsculas en usernames', function (string $username) {
    $this->actingAs($this->fixtures['usuario'])->from('/usuarios/crear')->post('/usuarios', [
        'username' => $username, 'rol_id' => $this->fixtures['usuario']->rol_id,
        'password' => 'Mañana#2026', 'password_confirmation' => 'Mañana#2026',
    ])->assertRedirect('/usuarios/crear')->assertSessionHasErrors('username')
        ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
    expect(Usuario::count())->toBe(1);
})->with(['turn mañana', 'nombre con espacio', 'usuario@@', 'turn_Ñ', 'Turn_mañana']);

test('validación UNIQUE rechaza el alias con o sin ñ de una cuenta existente', function (string $existente, string $nuevo) {
    $this->fixtures['usuario']->update(['username' => $existente]);
    $this->actingAs($this->fixtures['usuario'])->from('/usuarios/crear')->post('/usuarios', [
        'username' => $nuevo, 'rol_id' => $this->fixtures['usuario']->rol_id,
        'password' => 'Mañana#2026', 'password_confirmation' => 'Mañana#2026',
    ])->assertRedirect('/usuarios/crear')->assertSessionHasErrors(['username' => 'Ese nombre de usuario ya está en uso.']);
    expect(Usuario::count())->toBe(1);
})->with([['turn_manana', 'turn_mañana'], ['turn_mañana', 'turn_manana']]);

dataset('passwords Unicode permitidos', [
    'ejemplo solicitado' => ['Mañana#2026'],
    'ocho caracteres Unicode variados' => ['Ñáéíóú中🙂'],
    'solo minúsculas' => ['abcdefgh'],
    'solo números' => ['12345678'],
    'ocho eñes' => [str_repeat('ñ', 8)],
    '72 bytes con acentos' => [str_repeat('á', 36)],
    '72 bytes con emoji' => [str_repeat('🙂', 18)],
]);

test('crear y cambiar aceptan Unicode sin combinaciones obligatorias y autentican la clave exacta', function (string $password, bool $cambiar) {
    $this->actingAs($this->fixtures['usuario']);
    if ($cambiar) {
        $this->patch('/usuarios/'.$this->fixtures['usuario']->id.'/password', [
            'password' => $password, 'password_confirmation' => $password,
        ])->assertRedirect('/usuarios')->assertSessionHasNoErrors();
        $usuario = $this->fixtures['usuario']->fresh();
    } else {
        $this->post('/usuarios', [
            'username' => 'cuenta_unicode', 'rol_id' => $this->fixtures['usuario']->rol_id,
            'password' => $password, 'password_confirmation' => $password,
        ])->assertRedirect('/usuarios')->assertSessionHasNoErrors();
        $usuario = Usuario::where('username', 'cuenta_unicode')->sole();
    }
    expect(Hash::check($password, $usuario->password_hash))->toBeTrue();
    Auth::forgetGuards();
    $this->flushSession();
    $this->post('/login', ['username' => $usuario->username, 'password' => $password])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($usuario);
})->with('passwords Unicode permitidos')->with(['crear' => false, 'cambiar' => true]);

test('crear y cambiar rechazan menos de ocho caracteres más de 72 bytes y confirmación inválida', function (string $password, ?string $confirmation, bool $cambiar) {
    $this->actingAs($this->fixtures['usuario']);
    $antes = $this->fixtures['usuario']->password_hash;
    $payload = ['password' => $password, 'password_confirmation' => $confirmation];
    $url = $cambiar ? '/usuarios/'.$this->fixtures['usuario']->id.'/password' : '/usuarios/crear';
    $response = $cambiar
        ? $this->from($url)->patch($url, $payload)
        : $this->from($url)->post('/usuarios', $payload + ['username' => 'cuenta_unicode', 'rol_id' => $this->fixtures['usuario']->rol_id]);
    $response->assertRedirect($url)->assertSessionHasErrors('password')
        ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
    expect(Usuario::count())->toBe(1)->and($this->fixtures['usuario']->fresh()->password_hash)->toBe($antes);
    $this->withCookie(config('session.cookie'), session()->getId());
    $html = $this->get($url)->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    foreach ($xpath->query('//input[@name="password" or @name="password_confirmation"]') as $input) {
        expect($input->getAttribute('type'))->toBe('password')
            ->and($input->getAttribute('value'))->toBe('')
            ->and($input->getAttribute('minlength'))->toBe('8');
    }
})->with([
    'siete ASCII' => ['abcdefg', 'abcdefg'],
    'siete Unicode aunque ocupen catorce bytes' => [str_repeat('ñ', 7), str_repeat('ñ', 7)],
    '73 bytes ASCII' => [str_repeat('x', 73), str_repeat('x', 73)],
    '74 bytes Unicode' => [str_repeat('á', 37), str_repeat('á', 37)],
    '76 bytes emoji' => [str_repeat('🙂', 19), str_repeat('🙂', 19)],
    'confirmación ausente' => ['Mañana#2026', null],
    'confirmación distinta' => ['Mañana#2026', 'Manana#2026'],
    'carácter nulo' => ["Mañana\0#2026", "Mañana\0#2026"],
])->with(['crear' => false, 'cambiar' => true]);

test('RateLimiter comparte contador para grafías que MariaDB resuelve a la misma cuenta', function () {
    $this->fixtures['usuario']->update(['username' => 'turn_mañana']);
    foreach (['turn_manana', 'TURN_MAÑANA', 'turn_mañana', "turn_man\u{0303}ana", 'Turn_Manana'] as $username) {
        expect(Usuario::where('username', $username)->sole()->id)->toBe($this->fixtures['usuario']->id);
        $this->from('/login')->post('/login', ['username' => $username, 'password' => 'incorrecta-ficticia'])
            ->assertSessionHasErrors(['username' => 'Las credenciales ingresadas no son correctas.']);
    }
    $this->from('/login')->post('/login', ['username' => 'turn_mañana', 'password' => 'clave-ficticia-sprint4'])
        ->assertRedirect('/login')->assertSessionHasErrors('username');
    expect(session('errors')->first('username'))->toStartWith('Demasiados intentos.');
    $this->assertGuest();
});

test('RateLimiter mantiene contadores separados para cuentas distintas según MariaDB', function (string $primero, string $segundo) {
    $this->fixtures['usuario']->update(['username' => $primero]);
    $otro = Usuario::create([
        'username' => $segundo, 'rol_id' => $this->fixtures['usuario']->rol_id,
        'password_hash' => $this->fixtures['usuario']->password_hash, 'empleado_id' => null,
    ]);
    expect(Usuario::where('username', $primero)->sole()->id)->not->toBe($otro->id);
    for ($i = 0; $i < 5; $i++) {
        $this->from('/login')->post('/login', ['username' => $primero, 'password' => 'incorrecta-ficticia']);
    }
    $this->from('/login')->post('/login', ['username' => $primero, 'password' => 'clave-ficticia-sprint4'])
        ->assertSessionHasErrors('username');
    expect(session('errors')->first('username'))->toStartWith('Demasiados intentos.');
    $this->post('/login', ['username' => $segundo, 'password' => 'clave-ficticia-sprint4'])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($otro);
})->with([
    'nuevos nombres con ñ' => ['turn_mañana', 'señor_pan'],
    // Login conserva compatibilidad con cuentas heredadas fuera del alfabeto de creación.
    'heredado con a cirílica distinta de a latina' => ['usuario_а', 'usuario_a'],
]);

test('UI informa ñ permitida y política Unicode de ocho caracteres en ambos formularios', function () {
    $this->actingAs($this->fixtures['usuario'])->get('/usuarios/crear')->assertOk()
        ->assertSeeText('Se permite ñ.')->assertDontSeeText('Sin espacios ni ñ.');
    foreach (['/usuarios/crear', '/usuarios/'.$this->fixtures['usuario']->id.'/password'] as $url) {
        $response = $this->get($url)->assertOk()->assertSeeText('Mínimo 8 caracteres y máximo 72 bytes.');
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new DOMXPath($document);
        $inputs = $xpath->query('//input[@type="password"]');
        expect($inputs->length)->toBe(2);
        foreach ($inputs as $input) {
            expect($input->getAttribute('minlength'))->toBe('8')->and($input->getAttribute('value'))->toBe('');
        }
    }
});
