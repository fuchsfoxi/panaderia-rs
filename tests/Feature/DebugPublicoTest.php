<?php

use Illuminate\Support\Facades\Route;

test('la configuración efectiva desactiva debug también en el entorno local expuesto', function () {
    expect(config('app.debug'))->toBeFalse();
});

test('una excepción pública no revela mensaje interno rutas ni trazas', function (bool $json) {
    Route::get('/_tests/error-publico', fn () => throw new RuntimeException('diagnostico-ficticio-no-publicar'));
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'Accept' => $json ? 'application/json' : 'text/html',
    ]);
    $respuesta = $this->get('http://panaderia-publica.example.test/_tests/error-publico')->assertStatus(500);
    $contenido = $respuesta->getContent();
    foreach (['diagnostico-ficticio-no-publicar', 'vendor/laravel', '"trace"'] as $datoInterno) {
        expect(str_contains($contenido, $datoInterno))->toBeFalse();
    }
    if ($json) {
        $respuesta->assertExactJson(['message' => 'Server Error']);
    }
})->with([false, true]);
