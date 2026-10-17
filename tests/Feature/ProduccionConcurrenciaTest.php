<?php

use App\Models\Produccion;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

test('dos conexiones simultáneas reutilizan una cabecera y conservan ambos lotes', function (string $familia) {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere el runner MariaDB aislado.');
    }
    IsolatedMariaDb::connect();
    // DDL exclusivamente sobre la base ficticia cuya identidad se verificó arriba.
    expect(IsolatedMariaDb::migrateInNewDatabase())->toBe(0);
    $fixtures = IsolatedMariaDb::fixtures();
    $datos = [
        'categoria' => strtolower($familia), 'fecha' => '2026-10-11', 'turno_id' => $fixtures['turno']->id,
        'detalles' => [[
            'producto_id' => $fixtures['productos'][$familia]->id,
            'coches' => 1, 'latas_adicionales' => 2, 'hora_finalizacion' => '08:35',
            'cantidad' => 4, 'forma' => 'circular',
            'participantes' => [
                ['empleado_id' => $fixtures['empleados'][0]->id, 'rol_produccion_id' => $fixtures['roles']['Maestro']->id],
                ['empleado_id' => $fixtures['empleados'][1]->id, 'rol_produccion_id' => $fixtures['roles']['Ayudante']->id],
            ],
        ]],
    ];
    if ($familia === 'Pan') {
        unset($datos['detalles'][0]['cantidad']);
    }
    $procesos = [];
    try {
        DB::beginTransaction();
        DB::table('categorias')->where('id', $fixtures['productos'][$familia]->categoria_id)->lockForUpdate()->first();
        foreach (range(1, 2) as $numero) {
            $proceso = proc_open([PHP_BINARY, base_path('tests/Support/registrar-produccion-concurrente.php')], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $pipes, base_path());
            $procesos[] = [$proceso, $pipes];
            fwrite($pipes[0], json_encode(['datos' => $datos, 'usuario_id' => $fixtures['usuario']->id], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            expect(trim(fgets($pipes[1]) ?: ''))->toBe('LISTO');
        }
        // Ambos workers están listos; el lock de categoría los serializa al liberarlo.
        DB::commit();
        $ids = [];
        foreach ($procesos as [$proceso, $pipes]) {
            $ids[] = trim(stream_get_contents($pipes[1]));
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            expect(proc_close($proceso))->toBe(0, $error);
        }
        $procesos = [];
        expect($ids[0])->not->toBe('')->and($ids[1])->toBe($ids[0])
            ->and(Produccion::count())->toBe(1);
        $tabla = 'detalle_'.strtolower($familia);
        expect(DB::table($tabla)->count())->toBe(2)
            ->and(DB::table($tabla.'_empleado')->count())->toBe(4)
            ->and(DB::table($tabla)->pluck('registrado_por_usuario_id')->all())->toBe([$fixtures['usuario']->id, $fixtures['usuario']->id]);
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($procesos as [$proceso, $pipes]) {
            if (is_resource($proceso)) {
                proc_terminate($proceso);
                proc_close($proceso);
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
        }
        IsolatedMariaDb::restoreDatabase();
    }
})->with(['Pan', 'Torta', 'Bocadito']);
