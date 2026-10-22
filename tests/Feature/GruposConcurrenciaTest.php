<?php

use App\Actions\Personal\AdministrarGrupos;
use App\Models\GrupoProduccion;
use App\Models\Rol;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    expect(IsolatedMariaDb::migrateInNewDatabase())->toBe(0);
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->datos = ['nombre' => 'Equipo concurrente', 'categoria_id' => $this->fixtures['productos']['Pan']->categoria_id,
        'maestro_id' => $this->fixtures['empleados'][0]->id, 'integrantes' => [$this->fixtures['empleados'][1]->id], 'activo' => 1];
    $this->procesosGrupos = [];
});

afterEach(function () {
    foreach ($this->procesosGrupos ?? [] as $worker) {
        if (is_resource($worker['proceso'])) {
            proc_terminate($worker['proceso']);
            foreach ($worker['pipes'] as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($worker['proceso']);
        }
    }
    if (getenv('SPRINT4_CASE_DATABASE')) {
        IsolatedMariaDb::restoreDatabase();
    }
});

function iniciarWorkerGrupo(array $peticion): array
{
    $proceso = proc_open([PHP_BINARY, base_path('tests/Support/grupo-concurrente.php')], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, base_path());
    foreach ([1, 2] as $i) {
        stream_set_timeout($pipes[$i], 15);
    }
    fwrite($pipes[0], json_encode($peticion, JSON_THROW_ON_ERROR)."\n");
    fflush($pipes[0]);
    $linea = trim(fgets($pipes[1]) ?: '');
    expect($linea)->toStartWith('LISTO:');

    return ['proceso' => $proceso, 'pipes' => $pipes, 'conexion' => (int) substr($linea, 6)];
}

function esperarLockGrupo(int $conexion): void
{
    $limite = microtime(true) + 8;
    do {
        // MariaDB puede mostrar Statistics antes de publicar la trx en INNODB_TRX.
        $estado = DB::selectOne('SELECT INFO AS consulta FROM information_schema.PROCESSLIST WHERE ID = ?', [$conexion]);
        if ($estado && str_contains($estado->consulta ?? '', 'for update')
            && (str_contains($estado->consulta, 'from `empleados`') || str_contains($estado->consulta, 'from `grupos_produccion`'))) {
            return;
        }
        usleep(20000);
    } while (microtime(true) < $limite);
    throw new RuntimeException('El segundo worker no llegó a esperar el bloqueo esperado.');
}

function completarWorkerGrupo(array $worker): string
{
    fclose($worker['pipes'][0]);
    $resultado = trim(stream_get_contents($worker['pipes'][1]));
    $error = stream_get_contents($worker['pipes'][2]);
    fclose($worker['pipes'][1]);
    fclose($worker['pipes'][2]);
    expect(proc_close($worker['proceso']))->toBe(0, $error);

    return $resultado;
}

test('grupo y cambio laboral concurrentes respetan ambas precedencias sin datos obsoletos', function (bool $grupoPrimero, string $cambio) {
    $empleado = $this->fixtures['empleados'][1];
    $laboral = ['nombre_empleados' => $empleado->nombre_empleados,
        'rol_produccion_id' => $this->fixtures['roles'][$cambio === 'rol' ? 'Practicante' : 'Ayudante']->id,
        'activo' => $cambio === 'estado' ? 0 : 1];
    $peticionGrupo = ['operacion' => 'grupo', 'datos' => $this->datos, 'actor' => $this->fixtures['usuario']->id];
    $peticionEmpleado = ['operacion' => 'empleado', 'datos' => $laboral, 'empleado' => $empleado->id, 'actor' => $this->fixtures['usuario']->id];
    $primero = iniciarWorkerGrupo(($grupoPrimero ? $peticionGrupo : $peticionEmpleado) + ['pausar' => true]);
    $this->procesosGrupos[] = $primero;
    expect(trim(fgets($primero['pipes'][1]) ?: ''))->toBe('EMPLEADOS_BLOQUEADOS');
    $segundo = iniciarWorkerGrupo($grupoPrimero ? $peticionEmpleado : $peticionGrupo);
    $this->procesosGrupos[] = $segundo;
    esperarLockGrupo($segundo['conexion']);
    fwrite($primero['pipes'][0], "CONTINUAR\n");
    fflush($primero['pipes'][0]);
    expect(completarWorkerGrupo($primero))->toBe('GUARDADO');
    $resultado = completarWorkerGrupo($segundo);
    if ($grupoPrimero) {
        expect($resultado)->toBe('GUARDADO')->and(GrupoProduccion::count())->toBe(1)
            ->and(GrupoProduccion::with(GrupoProduccion::RELACIONES_COMPOSICION)->sole()->problemasComposicion())->not->toBe([]);
    } else {
        expect($resultado)->toStartWith('RECHAZADO:')->and(GrupoProduccion::count())->toBe(0)
            ->and(DB::table('grupo_integrantes')->count())->toBe(0);
    }
})->with([false, true])->with(['rol', 'estado']);

test('dos ediciones de un mismo grupo sincronizan desde los integrantes confirmados al adquirir el lock', function () {
    $grupo = app(AdministrarGrupos::class)->guardar($this->datos, $this->fixtures['usuario']->id);
    $datosPrimero = array_replace($this->datos, ['integrantes' => [$this->fixtures['empleados'][2]->id]]);
    $primero = iniciarWorkerGrupo(['operacion' => 'grupo', 'datos' => $datosPrimero, 'actor' => $this->fixtures['usuario']->id,
        'grupo' => $grupo->id, 'pausar' => true]);
    $this->procesosGrupos[] = $primero;
    expect(trim(fgets($primero['pipes'][1]) ?: ''))->toBe('EMPLEADOS_BLOQUEADOS');
    $segundo = iniciarWorkerGrupo(['operacion' => 'grupo', 'datos' => $this->datos, 'actor' => $this->fixtures['usuario']->id, 'grupo' => $grupo->id]);
    $this->procesosGrupos[] = $segundo;
    esperarLockGrupo($segundo['conexion']);
    fwrite($primero['pipes'][0], "CONTINUAR\n");
    fflush($primero['pipes'][0]);
    expect(completarWorkerGrupo($primero))->toBe('GUARDADO')->and(completarWorkerGrupo($segundo))->toBe('GUARDADO')
        ->and($grupo->fresh()->integrantes->modelKeys())->toBe($this->datos['integrantes'])
        ->and(DB::table('grupo_integrantes')->count())->toBe(1);
});
