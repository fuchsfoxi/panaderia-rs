<?php

use App\Models\Rol;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

test('indicadores se entregan ocultos con recursos compilados y formularios nativos intactos', function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere php tests/run-mariadb.php y npm run build.');
    }
    IsolatedMariaDb::connect();
    Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]);
    DB::beginTransaction();
    try {
        $f = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
        app(Vite::class)->useHotFile('/tmp/indicadores-sin-vite.hot');
        $rutas = ['login' => '/login', 'producto' => '/productos/crear', 'usuario' => '/usuarios/crear',
            'empleado' => '/empleados/crear', 'grupo' => '/grupos/crear', 'productos' => '/productos'];
        foreach ($rutas as $nombre => $ruta) {
            if ($nombre !== 'login') {
                $this->actingAs($f['usuario']);
            }
            $respuesta = $this->get($ruta)->assertOk()->assertSee('/build/assets/', false)
                ->assertDontSee('@vite/client', false);
            $html = $respuesta->getContent();
            $dom = new DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new DOMXPath($dom);
            expect($xpath->query('//*[@data-carga-pagina and @hidden and @role="status"]')->length)->toBe(1)
                ->and($xpath->query('//form[@aria-busy="true"]')->length)->toBe(0);
            if ($nombre !== 'productos') {
                $form = $xpath->query('//form[@data-carga-texto]')->item(0);
                expect($form->getAttribute('method'))->toBe('POST')
                    ->and($form->getAttribute('data-carga-texto'))->toBe($nombre === 'login' ? 'Ingresando…' : 'Guardando…')
                    ->and($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1)
                    ->and($xpath->query('.//button[@type="submit"]', $form)->length)->toBe(1);
            }
            if ($directorio = getenv('CARGA_BROWSER_HTML')) {
                if (! str_starts_with($directorio, '/tmp/')) {
                    throw new RuntimeException('Exportar solo en /tmp/.');
                }
                file_put_contents($directorio.'/'.$nombre.'.html', preg_replace('/(name="_token" value=")[^"]*"/', '$1csrf-ficticio"', $html));
            }
        }
    } finally {
        DB::rollBack();
    }
});
