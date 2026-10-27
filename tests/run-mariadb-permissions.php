<?php

// Servidor propio: nunca carga .env ni conecta al MariaDB del proyecto.
umask(0077);
$directory = sys_get_temp_dir().'/sprint4-permissions-'.bin2hex(random_bytes(6));
mkdir($directory, 0700);
$socket = $directory.'/mariadb.sock';
$reservation = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if (! $reservation) {
    throw new RuntimeException('No se pudo reservar un puerto de loopback para el ensayo.');
}
$port = (int) substr(strrchr(stream_socket_get_name($reservation, false), ':'), 1);
fclose($reservation);
$server = null;
$pdo = null;
$exitCode = 1;

try {
    $process = proc_open([
        'mariadb-install-db', '--no-defaults', '--datadir='.$directory.'/mariadb-data',
        '--auth-root-authentication-method=normal', '--skip-test-db', '--innodb-log-file-size=16M',
    ], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $directory.'/install.log', 'w'], 2 => ['file', $directory.'/install.log', 'a']], $pipes);
    if (! is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('No se pudo inicializar el servidor ficticio.');
    }
    $server = proc_open([
        'mariadbd', '--no-defaults', '--datadir='.$directory.'/mariadb-data',
        '--socket='.$socket, '--port='.$port, '--bind-address=127.0.0.1',
        '--pid-file='.$directory.'/pid', '--innodb-log-file-size=16M', '--innodb-buffer-pool-size=64M',
        '--event-scheduler=OFF', '--general-log=OFF', '--slow-query-log=OFF', '--skip-log-bin', '--performance-schema=OFF',
    ], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $directory.'/server.log', 'a'], 2 => ['file', $directory.'/server.log', 'a']], $pipes);
    if (! is_resource($server)) {
        throw new RuntimeException('No se pudo iniciar el servidor ficticio.');
    }
    $deadline = microtime(true) + 20;
    do {
        try {
            $pdo = new PDO('mysql:unix_socket='.$socket, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $exception) {
            usleep(100000);
        }
    } while (! $pdo && microtime(true) < $deadline && proc_get_status($server)['running']);
    if (! $pdo) {
        throw new RuntimeException('El socket ficticio no está disponible.');
    }
    $identity = $pdo->query('SELECT @@datadir datadir, @@port port, @@bind_address address')->fetch(PDO::FETCH_ASSOC);
    if (realpath($identity['datadir']) !== $directory.'/mariadb-data'
        || (int) $identity['port'] !== $port || $identity['address'] !== '127.0.0.1') {
        throw new RuntimeException('Identidad aislada incorrecta; no se crearán bases ni cuentas.');
    }
    $pdo->exec('CREATE DATABASE sprint4_models_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    fwrite(STDOUT, "Ensayo aislado: $directory; TCP exclusivamente loopback:$port.\n");
    $environment = array_replace(getenv(), [
        'SPRINT4_TEST_SOCKET' => $socket, 'SPRINT4_TEST_PORT' => (string) $port,
        'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'DB_CONNECTION' => 'mariadb', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => (string) $port,
        'DB_DATABASE' => 'sprint4_models_test', 'DB_USERNAME' => 'root', 'DB_PASSWORD' => '',
        'DB_SOCKET' => $socket, 'DB_URL' => '', 'LOG_CHANNEL' => 'null', 'PAO_DISABLE' => '1',
    ]);
    $process = proc_open([
        PHP_BINARY, dirname(__DIR__).'/artisan', 'test', 'tests/Feature/MariaDbRuntimePrivilegesTest.php',
        '--compact', '--colors=never', '--do-not-record-test-run-history', ...array_slice($argv, 1),
    ], [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__), $environment);
    $exitCode = is_resource($process) ? proc_close($process) : 1;
} catch (Throwable $exception) {
    // No propagar excepciones SQL con consultas o bindings.
    fwrite(STDERR, 'Ensayo detenido: '.get_class($exception).' / código '.$exception->getCode().".\n");
} finally {
    if ($pdo) {
        try {
            $pdo->exec('SHUTDOWN');
        } catch (PDOException $exception) {
            // El socket se cierra al detener la instancia propia.
        }
        $pdo = null;
    }
    if (is_resource($server)) {
        if (proc_get_status($server)['running']) {
            proc_terminate($server);
        }
        proc_close($server);
    }
    fwrite(STDOUT, "Instancia ficticia detenida; artefactos privados: $directory\n");
}
exit($exitCode);
