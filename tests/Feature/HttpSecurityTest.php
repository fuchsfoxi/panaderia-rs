<?php

use Illuminate\Database\QueryException;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Boost\BoostServiceProvider;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

test('las respuestas HTML impiden incrustar la aplicación en un frame ajeno', function (string $ruta) {
    $this->withoutVite()->get($ruta)
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
})->with(['/login', '/dashboard', '/ruta-inexistente']);

test('las cabeceras conservan las políticas CSP que ya restringen una respuesta', function () {
    Route::get('/_tests/csp-existente', fn () => response('contenido ficticio')->header('Content-Security-Policy', "default-src 'none'; sandbox"));
    $response = $this->get('/_tests/csp-existente')->assertOk();
    expect($response->headers->all('Content-Security-Policy'))->toBe([
        "default-src 'none'; sandbox", "frame-ancestors 'none'",
    ]);
});

test('el entorno local no publica la recolección de logs de desarrollo', function () {
    // Boost normalmente no arranca en testing; comprobar también su arranque local.
    app()->detectEnvironment(fn () => 'local');
    $provider = new BoostServiceProvider(app());
    $provider->register();
    $provider->boot(app(Router::class));
    $this->postJson('/_boost/browser-logs', ['logs' => []])->assertNotFound();
});

test('los errores SQL se registran sin consulta bindings ni credenciales', function () {
    $marca = 'dato-sensible-sintetico-'.bin2hex(random_bytes(8));
    Route::get('/_tests/error-sql-seguridad', fn () => throw new QueryException(
        'testing', 'update usuarios_sistema set password_hash = ?', [$marca], new PDOException('error ficticio'),
    ));
    $handler = new TestHandler;
    Log::swap(new Illuminate\Log\Logger(new Logger('security-test', [$handler])));
    $this->getJson('/_tests/error-sql-seguridad')->assertStatus(500)
        ->assertDontSee($marca)->assertExactJson(['message' => 'Server Error']);
    expect($handler->getRecords())->toHaveCount(1);
    $record = $handler->getRecords()[0];
    expect(str_contains($record->message, $marca))->toBeFalse()
        ->and(str_contains($record->message, 'update usuarios_sistema'))->toBeFalse()
        ->and(array_key_exists('exception', $record->context))->toBeFalse();
});

test('la cookie de sesión conserva HttpOnly SameSite y Secure según HTTPS confiable', function (string $peer, bool $forwarded, bool $secure) {
    $this->withoutVite()->withServerVariables(['REMOTE_ADDR' => $peer]);
    if ($forwarded) {
        $this->withHeader('X-Forwarded-Proto', 'https');
    }
    $response = $this->get('http://panaderia-seguridad.example.test/login')->assertOk();
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->isSecure())->toBe($secure);
})->with([
    'HTTP local' => ['127.0.0.1', false, false],
    'HTTPS por proxy local' => ['127.0.0.1', true, true],
    'forwarding remoto ignorado' => ['192.0.2.10', true, false],
]);
