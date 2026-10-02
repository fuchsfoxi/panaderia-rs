<?php

// Crea un servidor desechable sin red. Nunca carga .env ni conecta al servidor del proyecto.
$directory = sys_get_temp_dir().'/sprint4-'.bin2hex(random_bytes(6));
mkdir($directory, 0700);
$socket = $directory.'/mariadb.sock';
$server = null;
$pdo = null;
$exitCode = 1;

function runSprint4Command(array $command, ?array $environment = null): int
{
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__), $environment);
    if (! is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar el comando de pruebas.');
    }

    return proc_close($process);
}

try {
    if (runSprint4Command([
        'mariadb-install-db', '--no-defaults', '--datadir='.$directory.'/mariadb-data',
        '--auth-root-authentication-method=normal', '--skip-test-db',
    ]) !== 0) {
        throw new RuntimeException('No se pudo inicializar MariaDB temporal; se requieren sus binarios instalados.');
    }
    $server = proc_open([
        'mariadbd', '--no-defaults', '--datadir='.$directory.'/mariadb-data',
        '--socket='.$socket, '--skip-networking', '--pid-file='.$directory.'/mariadb.pid',
        '--log-error='.$directory.'/mariadb.log', '--innodb-buffer-pool-size=64M',
    ], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $directory.'/server-output.log', 'a'], 2 => ['file', $directory.'/server-output.log', 'a']], $pipes);
    if (! is_resource($server)) {
        throw new RuntimeException('No se pudo iniciar el servidor temporal.');
    }
    $deadline = microtime(true) + 15;
    do {
        try {
            $pdo = new PDO('mysql:unix_socket='.$socket, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $exception) {
            usleep(100000);
        }
    } while (! $pdo && microtime(true) < $deadline && proc_get_status($server)['running']);
    if (! $pdo) {
        throw new RuntimeException('MariaDB temporal no está disponible; revisar '.$directory.'/mariadb.log');
    }
    foreach (['sprint4_models_test', 'sprint4_migration_test'] as $database) {
        $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
    fwrite(STDOUT, "Servidor temporal: $directory; migraciones exclusivamente en dos bases ficticias.\n");
    $environment = array_replace(getenv(), ['SPRINT4_TEST_SOCKET' => $socket]);
    $exitCode = runSprint4Command([PHP_BINARY, dirname(__DIR__).'/artisan', 'test', ...array_slice($argv, 1)], $environment);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
} finally {
    if ($pdo) {
        try {
            $pdo->exec('SHUTDOWN');
        } catch (PDOException $exception) {
            // El servidor puede cerrar el socket antes de responder a SHUTDOWN.
        }
        $pdo = null;
    }
    if (is_resource($server)) {
        if (proc_get_status($server)['running']) {
            proc_terminate($server);
        }
        proc_close($server);
    }
    // Conservar logs y datos ficticios para inspección; no hay un proceso activo.
    fwrite(STDOUT, "Instancia temporal detenida. Artefactos: $directory\n");
}
exit($exitCode);
