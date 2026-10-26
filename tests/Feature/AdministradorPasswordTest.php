<?php

use App\Models\Rol;
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
    $this->admin = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR)['usuario'];
    $this->withoutVite();
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

test('Administrador cambia su propia contraseña por formulario y revoca sus dos sesiones previas', function () {
    config(['session.driver' => 'database', 'session.connection' => 'sprint4_test']);
    $freshSession = function () {
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        Auth::forgetGuards();
    };
    $before = $this->admin->getRawOriginal();
    $sessions = [];
    for ($i = 0; $i < 2; $i++) {
        $freshSession();
        $this->post('/login', ['username' => $this->admin->username, 'password' => 'clave-ficticia-sprint4'])
            ->assertRedirect('/dashboard');
        $sessions[] = session()->getId();
    }
    expect($sessions[0])->not->toBe($sessions[1]);
    $url = '/usuarios/'.$this->admin->id.'/password';
    $this->withCookie(config('session.cookie'), $sessions[1]);
    $this->get($url)->assertOk()->assertSee('password_confirmation', false)
        ->assertDontSee($before['password_hash']);
    $new = Str::random(40);
    $this->patch($url, ['password' => $new, 'password_confirmation' => $new])
        ->assertRedirect('/usuarios')->assertSessionHasNoErrors();
    $after = $this->admin->fresh()->getRawOriginal();
    expect(Hash::check($new, $after['password_hash']))->toBeTrue()
        ->and(Hash::check('clave-ficticia-sprint4', $after['password_hash']))->toBeFalse();
    unset($before['password_hash'], $before['updated_at'], $after['password_hash'], $after['updated_at']);
    expect($after)->toBe($before);

    foreach ($sessions as $id) {
        $freshSession();
        $this->withCookie(config('session.cookie'), $id)->get('/usuarios')->assertRedirect('/login');
        $this->assertGuest();
    }

    $freshSession();
    $this->from('/login')->post('/login', ['username' => $this->admin->username, 'password' => 'clave-ficticia-sprint4'])
        ->assertRedirect('/login');
    $this->assertGuest();
    $freshSession();
    // La visita protegida rechazada conserva /usuarios como destino previsto.
    $this->post('/login', ['username' => $this->admin->username, 'password' => $new])->assertRedirect('/usuarios');
    $this->assertAuthenticatedAs($this->admin);
});
