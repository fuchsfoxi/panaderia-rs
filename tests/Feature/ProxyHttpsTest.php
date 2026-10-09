<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Rutas solo de prueba: atraviesan el middleware global real del checkout.
    Route::get('/_tests/proxy', fn (Request $request) => response()->json([
        'secure' => $request->isSecure(),
        'host' => $request->getHost(),
        'port' => $request->getPort(),
        'ip' => $request->ip(),
        'internal_port' => $request->server('SERVER_PORT'),
        'login_url' => route('login'),
        'asset_url' => asset('images/icono_login.png'),
    ]));
    Route::get('/_tests/proxy/redirect', fn () => redirect()->route('login'));
});

test('HTTPS forwarded desde loopback conserva el host público y el esquema de URLs y redirects', function (string $proxy) {
    $this->withServerVariables(['REMOTE_ADDR' => $proxy])
        ->withHeaders(['X-Forwarded-Proto' => 'https']);

    // HTTP interno, Host público conservado por cloudflared y sin puerto forwarded.
    $this->get('http://panaderia-proxy.example.test/_tests/proxy')
        ->assertOk()->assertJson([
            'secure' => true,
            'host' => 'panaderia-proxy.example.test',
            'port' => 443,
            'ip' => $proxy,
            'internal_port' => 80,
            'login_url' => 'https://panaderia-proxy.example.test/login',
            'asset_url' => 'https://panaderia-proxy.example.test/images/icono_login.png',
        ]);
    $this->get('http://panaderia-proxy.example.test/_tests/proxy/redirect')
        ->assertRedirect('https://panaderia-proxy.example.test/login');
})->with(['loopback IPv4' => '127.0.0.1', 'loopback IPv6' => '::1']);

test('loopback permite forwarding explícito de host puerto e IP', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'panaderia-proxy.example.test',
        'X-Forwarded-Port' => '8443',
        'X-Forwarded-For' => '203.0.113.20',
    ]);

    $this->get('http://127.0.0.1:8000/_tests/proxy')->assertOk()->assertJson([
        'secure' => true,
        'host' => 'panaderia-proxy.example.test',
        'port' => 8443,
        'ip' => '203.0.113.20',
        'internal_port' => 8000,
        'login_url' => 'https://panaderia-proxy.example.test:8443/login',
    ]);
    $this->get('http://127.0.0.1:8000/_tests/proxy/redirect')
        ->assertRedirect('https://panaderia-proxy.example.test:8443/login');
});

test('clientes fuera de loopback no pueden imponer protocolo host puerto o IP forwarded', function (string $cliente) {
    $this->withServerVariables(['REMOTE_ADDR' => $cliente])->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'untrusted.example.test',
        'X-Forwarded-Port' => '443',
        'X-Forwarded-For' => '203.0.113.20',
    ]);

    $this->get('http://127.0.0.1:8000/_tests/proxy')->assertOk()->assertJson([
        'secure' => false,
        'host' => '127.0.0.1',
        'port' => 8000,
        'ip' => $cliente,
        'login_url' => 'http://127.0.0.1:8000/login',
        'asset_url' => 'http://127.0.0.1:8000/images/icono_login.png',
    ]);
    $this->get('http://127.0.0.1:8000/_tests/proxy/redirect')
        ->assertRedirect('http://127.0.0.1:8000/login');
})->with(['IPv4 remoto' => '198.51.100.10', 'IPv6 remoto' => '2001:db8::10', 'LAN' => '192.168.1.10']);

test('localhost sin forwarded continúa generando HTTP', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);

    $this->get('http://127.0.0.1:8000/_tests/proxy')->assertOk()->assertJson([
        'secure' => false,
        'host' => '127.0.0.1',
        'port' => 8000,
        'ip' => '127.0.0.1',
        'login_url' => 'http://127.0.0.1:8000/login',
    ]);
    $this->get('http://127.0.0.1:8000/_tests/proxy/redirect')
        ->assertRedirect('http://127.0.0.1:8000/login');
});
