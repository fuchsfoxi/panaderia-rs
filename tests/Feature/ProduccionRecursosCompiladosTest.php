<?php

use App\Models\GrupoProduccion;
use App\Models\Rol;
use App\Models\Turno;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

// Verifica Blade con Vite real y permite exportar HTML ficticio para la prueba de navegador.
test('Producción usa recursos compilados sin servidor Vite', function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable y npm run build.');
    }
    IsolatedMariaDb::connect();
    Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]);
    DB::beginTransaction();
    try {
        $f = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
        Turno::create(['nombre_turnos' => 'Noche']);
        foreach (['Pan', 'Torta', 'Bocadito'] as $familia) {
            $grupo = GrupoProduccion::create(['nombre' => 'Equipo '.$familia, 'categoria_id' => $f['productos'][$familia]->categoria_id, 'maestro_id' => $f['empleados'][0]->id, 'activo' => true]);
            if ($familia !== 'Torta') {
                $grupo->integrantes()->attach($f['empleados'][1]->id);
            }
        }
        $this->actingAs($f['usuario']);
        app(Vite::class)->useHotFile('/tmp/panaderia-sin-vite.hot');
        $exportar = function (string $nombre, string $html): void {
            if ($directorio = getenv('PRODUCCION_BROWSER_HTML')) {
                if (! str_starts_with($directorio, '/tmp/')) {
                    throw new RuntimeException('Exportar fixtures solo en /tmp/.');
                }
                // Exportar únicamente ejemplos; nunca conservar tokens de sesión.
                $html = preg_replace('/(name="_token" value=")[^"]*"/', '$1csrf-ficticio"', $html);
                file_put_contents($directorio.'/'.$nombre.'.html', $html);
            }
        };
        foreach (['pan', 'torta', 'bocadito'] as $familia) {
            $response = $this->get('/produccion?categoria='.$familia)->assertOk()
                ->assertSee('/build/assets/', false)->assertDontSee('@vite/client', false)->assertDontSee(':5173', false)
                ->assertDontSee('data-exito-produccion', false);
            $exportar($familia, $response->getContent());
        }
        $this->post('/produccion/torta', [
            'categoria' => 'torta', 'fecha' => '2026-10-23', 'turno_id' => $f['turno']->id,
            'detalles' => [['producto_id' => $f['productos']['Torta']->id, 'cantidad' => 2,
                'forma' => 'circular', 'hora_vitrina' => '14:15',
                'participantes' => [['empleado_id' => $f['empleados'][0]->id, 'rol_produccion_id' => $f['roles']['Maestro']->id]],
            ]],
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Torta guardada');
        $response = $this->get('/produccion?categoria=torta')->assertOk()->assertSee('data-exito-produccion', false)->assertSeeText('Torta guardada');
        $exportar('exito', $response->getContent());
        $this->get('/produccion?categoria=torta')->assertDontSee('data-exito-produccion', false);
        $f['rol']->update(['nombre_roles' => Rol::OPERADOR]);
        $f['usuario']->update(['turno_id' => Turno::where('nombre_turnos', 'Noche')->sole()->id]);
        $this->actingAs($f['usuario']->fresh());
        $exportar('operador-noche', $this->get('/produccion')->assertOk()->getContent());
    } finally {
        DB::rollBack();
    }
});
