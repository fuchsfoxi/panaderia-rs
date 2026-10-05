<?php

use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\Usuario;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para probar navegación en MariaDB aislada.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    // index() consulta catálogos reales; solo Pan debe existir para renderizar.
    Categoria::create(['nombre_categorias' => 'Pan']);

    $this->withoutVite();
    // La cuenta y el empleado precargados siguen siendo fixtures en memoria
    // para verificar navegación y sesiones sin persistir datos de autenticación.
    $this->usuario = (new Usuario)->forceFill([
        'id' => 1,
        'username' => 'usuario.prueba',
        'password_hash' => Hash::make('clave-de-prueba'),
    ])->setRelation('empleado', new Empleado([
        'nombre_empleados' => 'Juan Pérez',
    ]));
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

dataset('paginas protegidas', ['dashboard', 'produccion.index', 'history.index']);

test('el autenticado ve el menú compartido y la sección activa', function (string $ruta) {
    $response = $this->actingAs($this->usuario)->get(route($ruta));
    $response->assertOk()->assertSeeText('Juan Pérez');

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//nav[@aria-label="Navegación principal"]')->length)->toBe(1);
    $links = $xpath->query('//nav[@aria-label="Navegación principal"]/a');
    expect($links->length)->toBe(3);

    foreach (['dashboard', 'produccion.index', 'history.index'] as $index => $destino) {
        $link = $links->item($index);
        expect($link->getAttribute('href'))->toBe(route($destino));
        expect($link->getAttribute('aria-current'))->toBe($destino === $ruta ? 'page' : '');
        expect(str_contains($link->getAttribute('class'), 'activo'))->toBe($destino === $ruta);
    }

    $logout = $xpath->query('//form[@class="sidebar-salir"]')->item(0);
    expect($logout->getAttribute('action'))->toBe(route('logout'));
    expect($logout->getAttribute('method'))->toBe('POST');
    expect($xpath->query('.//input[@name="_token"]', $logout)->length)->toBe(1);
    expect($xpath->query('.//button[@type="submit"]', $logout)->item(0)->textContent)->toBe('Cerrar sesión');

    // Se revisa el HTML original: un parser puede reparar y ocultar forms anidados.
    preg_match_all('~</?form\b[^>]*>~i', $response->getContent(), $tags);
    $depth = 0;
    foreach ($tags[0] as $tag) {
        $depth += str_starts_with(strtolower($tag), '</') ? -1 : 1;
        expect($depth)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    }
    expect($depth)->toBe(0);

    if ($ruta === 'produccion.index') {
        $produccion = $xpath->query('//form[@id="formulario-produccion"]')->item(0);
        expect($produccion->getAttribute('action'))->toBe(route('produccion.store'));
        expect($produccion->getAttribute('method'))->toBe('POST');
        expect($produccion->getAttribute('enctype'))->toBe('multipart/form-data');
        expect($xpath->query('.//input[@name="_token"]', $produccion)->length)->toBe(1);
    }
})->with('paginas protegidas');

test('el menú muestra username si no hay empleado relacionado', function () {
    $this->usuario->setRelation('empleado', null);
    $this->actingAs($this->usuario)->get(route('dashboard'))
        ->assertOk()->assertSeeText('usuario.prueba');
});

test('un invitado es redirigido al login desde cada página', function (string $ruta) {
    $this->get(route($ruta))->assertRedirect(route('login'));
    $this->assertGuest();
})->with('paginas protegidas');

test('las páginas autenticadas no se almacenan en la caché HTTP', function (string $ruta) {
    $response = $this->actingAs($this->usuario)->get(route($ruta));
    $response->assertOk()->assertHeader('Pragma', 'no-cache')->assertHeader('Expires', '0');

    foreach (['private', 'no-store', 'no-cache', 'must-revalidate'] as $directive) {
        expect($response->headers->hasCacheControlDirective($directive))->toBeTrue();
    }
})->with('paginas protegidas');

test('login y las redirecciones de invitados tampoco se almacenan', function () {
    $this->get(route('login'))->assertOk()->assertHeader('Pragma', 'no-cache');

    foreach (['login', 'dashboard', 'produccion.index', 'history.index'] as $ruta) {
        $response = $this->get(route($ruta));
        expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
        if ($ruta !== 'login') {
            $response->assertRedirect(route('login'));
        }
    }
});

test('las páginas y las operaciones siguen protegidas por auth', function () {
    foreach (['dashboard', 'produccion.index', 'produccion.store', 'history.index', 'logout'] as $nombre) {
        expect(Route::getRoutes()->getByName($nombre)->gatherMiddleware())->toContain('auth');
    }
    expect(Route::getRoutes()->getByName('logout')->methods())->toBe(['POST']);
    $this->post(route('produccion.store'))->assertRedirect(route('login'));
    $this->post(route('logout'))->assertRedirect(route('login'));
});

test('logout invalida la sesión y hace inaccesibles las páginas protegidas', function () {
    $this->actingAs($this->usuario)->withSession(['dato_privado' => 'anterior']);
    $session = app('session.store');
    $oldId = $session->getId();
    $oldToken = $session->token();

    $response = $this->post(route('logout'));
    $response->assertRedirect(route('login'))->assertSessionMissing('dato_privado');
    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
    $this->assertGuest();
    expect($session->getId())->not->toBe($oldId);
    expect($session->token())->toBeString()->not->toBe($oldToken);

    foreach (['dashboard', 'produccion.index', 'history.index'] as $ruta) {
        Auth::forgetGuards();
        $this->get(route($ruta))->assertRedirect(route('login'));
    }
    $this->get(route('login'))->assertOk();
});

test('GET logout no cierra la sesión', function () {
    $this->actingAs($this->usuario)->get(route('logout'))->assertStatus(405);
    $this->assertAuthenticatedAs($this->usuario);
});

test('logout exige protección CSRF válida con el middleware activo', function (?string $token, int $status) {
    // Laravel omite CSRF durante tests. Solo se desactiva ese bypass para
    // ejecutar la comprobación real, sin header de origen aceptado.
    $middleware = Mockery::mock(PreventRequestForgery::class, [app(), app('encrypter')])
        ->makePartial()->shouldAllowMockingProtectedMethods();
    $middleware->shouldReceive('runningUnitTests')->andReturnFalse();
    app()->instance(PreventRequestForgery::class, $middleware);

    $this->actingAs($this->usuario)->withSession(['_token' => 'token-valido']);
    $response = $this->post(route('logout'), $token === null ? [] : ['_token' => $token]);
    $response->assertStatus($status);

    if ($status === 302) {
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    } else {
        $this->assertAuthenticatedAs($this->usuario);
    }
})->with([
    'sin token' => [null, 419],
    'token incorrecto' => ['incorrecto', 419],
    'token válido' => ['token-valido', 302],
]);

test('login conserva el acceso al dashboard y el destino solicitado', function (?string $destino) {
    // Solo se simula la lectura de la cuenta; la validación del hash, guard,
    // controlador y sesión son reales. No se usa una base de datos compartida.
    $provider = Mockery::mock(EloquentUserProvider::class, [app('hash'), Usuario::class])->makePartial();
    $provider->shouldReceive('retrieveByCredentials')->once()
        ->with(['username' => 'usuario.prueba', 'password' => 'clave-de-prueba'])
        ->andReturn($this->usuario);
    Auth::guard()->setProvider($provider);

    $this->get(route('login'))->assertOk();
    if ($destino !== null) {
        $this->get(route($destino))->assertRedirect(route('login'));
    }
    $oldId = app('session.store')->getId();

    $this->post(route('login'), [
        'username' => 'usuario.prueba',
        'password' => 'clave-de-prueba',
    ])->assertRedirect(route($destino ?? 'dashboard'));
    $this->assertAuthenticatedAs($this->usuario);
    expect(app('session.store')->getId())->not->toBe($oldId);
    $this->get(route($destino ?? 'dashboard'))->assertOk();
    $this->get(route('login'))->assertRedirect(route('dashboard'));
})->with([null, 'produccion.index', 'history.index']);
