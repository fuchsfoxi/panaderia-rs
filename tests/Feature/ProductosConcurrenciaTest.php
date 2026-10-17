<?php

use App\Models\Producto;
use App\Models\UnidadMedida;
use Database\Seeders\UnidadMedidaSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

require_once __DIR__.'/../Support/productos-fixtures.php';

test('edición estado y primera producción se serializan preservando historia', function (string $familia, string $campo, bool $produccionPrimero) {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB temporal y dos procesos reales.');
    }
    IsolatedMariaDb::connect();
    expect(IsolatedMariaDb::migrateInNewDatabase())->toBe(0);
    $fixtures = IsolatedMariaDb::fixtures();
    (new UnidadMedidaSeeder)->run();
    $producto = $fixtures['productos'][$familia];
    $datosEdicion = datosProductoDePrueba($fixtures, $familia);
    if ($campo === 'categoria_id') {
        $destino = $familia === 'Pan' ? 'Torta' : 'Pan';
        $datosEdicion['categoria_id'] = $fixtures['productos'][$destino]->categoria_id;
        $datosEdicion['panes_por_lata'] = $destino === 'Pan' ? 24 : null;
        $datosEdicion['unidad_registro'] = $destino === 'Pan' ? 'LATA' : 'UNIDAD';
        unset($datosEdicion['unidad_medida_id']);
    } elseif ($campo === 'unidad_medida_id') {
        $datosEdicion['unidad_medida_id'] = UnidadMedida::where('codigo', ($familia === 'Pan' ? UnidadMedida::LATA : UnidadMedida::UNIDAD))->sole()->id;
        if ($familia === 'Pan') {
            $datosEdicion['unidad_medida_id'] = UnidadMedida::where('codigo', UnidadMedida::UNIDAD)->sole()->id;
            $datosEdicion['unidad_registro'] = UnidadMedida::UNIDAD;
            $datosEdicion['panes_por_lata'] = null;
        }
    } else {
        $datosEdicion = ['activo' => false];
    }
    $edicion = ['operacion' => 'editar', 'producto_id' => $producto->id, 'datos' => $datosEdicion];
    $registro = ['operacion' => 'produccion', 'usuario_id' => $fixtures['usuario']->id, 'datos' => datosProduccionProducto($fixtures, $familia)];
    $procesos = [];
    $iniciar = function (array $entrada) use (&$procesos): array {
        $proceso = proc_open([PHP_BINARY, base_path('tests/Support/producto-concurrente.php')], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $procesos[] = [$proceso, $pipes];
        stream_set_timeout($pipes[1], 15);
        fwrite($pipes[0], json_encode($entrada, JSON_THROW_ON_ERROR)."\n");
        $linea = trim(fgets($pipes[1]) ?: '');
        expect($linea)->toStartWith('LISTO:');

        return [$proceso, $pipes, (int) substr($linea, 6)];
    };
    try {
        // El producto abierto para editar no tenía historia cuando se preparó el payload.
        $primero = $iniciar(($produccionPrimero ? $registro : $edicion) + ['pausar' => true]);
        expect(trim(fgets($primero[1][1]) ?: ''))->toBe('PRODUCTO_BLOQUEADO');
        $segundo = $iniciar(($produccionPrimero ? $edicion : $registro) + ['pausar' => false]);
        $deadline = microtime(true) + 5;
        do {
            $espera = DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.PROCESSLIST WHERE ID = ? AND (INFO LIKE '%from `productos`%for update' OR INFO LIKE '%from `categorias`%for update' OR INFO LIKE '%from `categorias`%lock in share mode')", [$segundo[2]])->total;
            if (! $espera) {
                usleep(10000);
            }
        } while (! $espera && microtime(true) < $deadline);
        // MariaDB puede mostrar Statistics antes de asignar una trx de InnoDB al SELECT.
        // La primera conexión mantiene categoría/producto y la segunda intenta bloquear ese contexto.
        expect((int) $espera)->toBe(1, 'La segunda conexión debe estar intentando bloquear categoría/producto antes de liberar la primera.');
        fwrite($primero[1][0], "CONTINUAR\n");
        foreach ([$primero, $segundo] as $worker) {
            fclose($worker[1][0]);
        }
        $resultados = [];
        foreach ([$primero, $segundo] as [$proceso, $pipes]) {
            $salida = trim(stream_get_contents($pipes[1]));
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            expect(proc_close($proceso))->toBe(0, $error);
            expect(json_validate($salida))->toBeTrue($salida.'; '.$error);
            $resultados[] = json_decode($salida, true, flags: JSON_THROW_ON_ERROR);
        }
        $procesos = [];
        expect($resultados[0]['resultado'])->toBe('GUARDADO');
        if ($produccionPrimero && ($campo === 'categoria_id' || ($campo === 'unidad_medida_id' && $familia === 'Pan'))) {
            expect($resultados[1]['resultado'])->toBe('RECHAZADO')->and($resultados[1]['campos'])->toContain($campo)
                ->and($producto->fresh()->categoria_id)->toBe($producto->categoria_id)
                ->and($producto->fresh()->unidad_medida_id)->toBe($producto->unidad_medida_id);
        } elseif ($produccionPrimero) {
            expect($resultados[1]['resultado'])->toBe('GUARDADO');
            if ($campo === 'activo') {
                expect($producto->fresh()->activo)->toBeFalse();
            }
        } else {
            expect($producto->fresh()->getAttribute($campo))->toBe($datosEdicion[$campo]);
            expect($resultados[1]['resultado'])->toBe($campo === 'unidad_medida_id' && $familia !== 'Pan' ? 'GUARDADO' : 'RECHAZADO');
        }
        expect(DB::table('detalle_'.strtolower($familia))->count())->toBe($produccionPrimero || ($campo === 'unidad_medida_id' && $familia !== 'Pan') ? 1 : 0);
        if ($familia === 'Pan' && $produccionPrimero && $campo === 'unidad_medida_id') {
            expect(DB::table('detalle_pan')->sole()->unidad_medida_id)->toBe($producto->unidad_medida_id);
        }
    } finally {
        foreach ($procesos as [$proceso, $pipes]) {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($proceso)) {
                proc_terminate($proceso);
                proc_close($proceso);
            }
        }
        IsolatedMariaDb::restoreDatabase();
    }
})->with(['Pan', 'Torta', 'Bocadito'])->with(['categoria_id', 'unidad_medida_id', 'activo'])->with([true, false]);
