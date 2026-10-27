<?php

use App\Actions\Personal\AdministrarEmpleados;
use App\Actions\Personal\AdministrarGrupos;
use App\Actions\Usuarios\AdministrarUsuarios;
use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Producto;
use App\Models\Rol;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\IsolatedMariaDb;
use Tests\Support\MariaDbRuntimePrivileges;

require_once __DIR__.'/../Support/productos-fixtures.php';

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET') || ! getenv('SPRINT4_TEST_PORT')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb-permissions.php.');
    }
    IsolatedMariaDb::connect();
    $server = DB::selectOne('SELECT @@port port, @@bind_address address');
    expect((int) $server->port)->toBe((int) getenv('SPRINT4_TEST_PORT'))
        ->and($server->address)->toBe('127.0.0.1');
    expect(IsolatedMariaDb::migrateInNewDatabase())->toBe(0);
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->root = DB::connection('sprint4_test');
    $this->database = $this->root->getDatabaseName();
    $this->account = 'rs_app_'.bin2hex(random_bytes(6));
    $password = bin2hex(random_bytes(32));
    $pdo = $this->root->getPdo();
    $account = $pdo->quote($this->account)."@'127.0.0.1'";
    $pdo->exec('CREATE USER '.$account.' IDENTIFIED BY '.$pdo->quote($password));
    foreach (MariaDbRuntimePrivileges::tables() as $table => $privileges) {
        $pdo->exec('GRANT '.implode(', ', $privileges).' ON `'.$this->database.'`.`'.$table.'` TO '.$account);
    }
    config([
        'database.default' => 'runtime_probe',
        'database.connections.runtime_probe' => [
            'driver' => 'mariadb', 'url' => null, 'unix_socket' => '', 'host' => '127.0.0.1',
            'port' => (int) getenv('SPRINT4_TEST_PORT'), 'database' => $this->database,
            'username' => $this->account, 'password' => $password,
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
        ],
        'session.driver' => 'database', 'session.connection' => null,
        'cache.default' => 'database', 'cache.stores.database.connection' => null,
        'cache.stores.database.lock_connection' => null,
        'queue.default' => 'database', 'queue.connections.database.connection' => null,
        'queue.batching.database' => 'runtime_probe', 'queue.failed.database' => 'runtime_probe',
    ]);
    DB::purge('runtime_probe');
    expect(DB::selectOne('SELECT CURRENT_USER() account')->account)->toBe($this->account.'@127.0.0.1');
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    Auth::forgetGuards();
    $this->withoutVite();
});

afterEach(function () {
    if (getenv('SPRINT4_TEST_PORT') && config('database.default') === 'runtime_probe') {
        DB::disconnect('runtime_probe');
        IsolatedMariaDb::restoreDatabase();
    }
});

test('cuenta restringida autentica y sirve las vistas con sesiones database', function () {
    $this->post('/login', ['username' => $this->fixtures['usuario']->username, 'password' => 'clave-ficticia-sprint4'])
        ->assertRedirect('/dashboard');
    $this->withCookie(config('session.cookie'), session()->getId());
    foreach (['/dashboard', '/produccion', '/history', '/productos', '/usuarios', '/empleados', '/grupos'] as $url) {
        $this->get($url)->assertOk();
    }
    expect(DB::table('sessions')->where('user_id', $this->fixtures['usuario']->id)->exists())->toBeTrue();
    $this->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

test('cuenta restringida escribe productos personal grupos y usuarios con los locks existentes', function () {
    $this->actingAs($this->fixtures['usuario'])->post('/productos', datosProductoDePrueba($this->fixtures))
        ->assertRedirect('/productos')->assertSessionHasNoErrors();
    $product = Producto::where('nombre_p', 'Producto nuevo')->sole();
    $this->patch('/productos/'.$product->id, array_replace(datosProductoDePrueba($this->fixtures), ['nombre_p' => 'Producto editado']))
        ->assertRedirect('/productos/'.$product->id.'/editar')->assertSessionHasNoErrors();
    $this->patch('/productos/'.$product->id.'/estado', ['activo' => 0])->assertRedirect('/productos');
    $actor = $this->fixtures['usuario']->id;
    $employee = app(AdministrarEmpleados::class)->guardar([
        'nombre_empleados' => 'Ayudante de ensayo', 'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id, 'activo' => true,
    ], $actor);
    app(AdministrarEmpleados::class)->estado($employee->id, false, $actor);
    $group = app(AdministrarGrupos::class)->guardar([
        'nombre' => 'Grupo de ensayo', 'categoria_id' => $this->fixtures['productos']['Pan']->categoria_id,
        'maestro_id' => $this->fixtures['empleados'][0]->id, 'integrantes' => [$this->fixtures['empleados'][1]->id], 'activo' => true,
    ], $actor);
    app(AdministrarGrupos::class)->guardar([
        'nombre' => 'Grupo editado', 'categoria_id' => $group->categoria_id, 'maestro_id' => $group->maestro_id,
        'integrantes' => [$this->fixtures['empleados'][2]->id], 'activo' => true,
    ], $actor, $group->id);
    $password = Str::random(40);
    $user = app(AdministrarUsuarios::class)->crear([
        'username' => 'runtime.ficticio', 'rol_id' => $this->fixtures['rol']->id, 'password' => $password,
    ], $actor);
    app(AdministrarUsuarios::class)->password($user->id, Str::random(40), $actor);
    app(AdministrarUsuarios::class)->estado($user->id, false, $actor);
    expect($employee->fresh()->activo)->toBeFalse()->and($user->fresh()->activo)->toBeFalse()
        ->and($group->fresh()->integrantes->sole()->id)->toBe($this->fixtures['empleados'][2]->id);
});

test('cuenta restringida registra y elimina lotes sin permisos DDL', function (string $family, string $model) {
    $payload = datosProduccionProducto($this->fixtures, $family);
    $this->actingAs($this->fixtures['usuario'])->post(route($family === 'Pan' ? 'produccion.store' : 'produccion.'.strtolower($family).'.store'), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();
    $detail = $model::sole();
    expect($detail->produccion->categoria_id)->toBe($this->fixtures['productos'][$family]->categoria_id);
    $this->delete('/history/'.strtolower($family).'/'.$detail->id, ['confirmado' => 1])
        ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    expect($model::count())->toBe(0)->and(DB::table('produccion')->count())->toBe(0);
})->with([['Pan', DetallePan::class], ['Torta', DetalleTorta::class], ['Bocadito', DetalleBocadito::class]]);

test('cuenta restringida mantiene cache locks colas fallos y lotes', function () {
    Cache::put('runtime-probe', 1, 60);
    expect(Cache::increment('runtime-probe'))->toBe(2);
    expect(Cache::add('runtime-probe', 3, 60))->toBeFalse();
    $lock = Cache::lock('runtime-probe-lock', 10);
    expect($lock->get())->toBeTrue();
    expect(Cache::lock('runtime-probe-lock', 10)->get())->toBeFalse();
    expect($lock->release())->toBeTrue();
    Cache::forget('runtime-probe');
    $queue = Queue::connection('database');
    $payload = json_encode(['uuid' => (string) Str::uuid(), 'displayName' => 'Ensayo ficticio', 'job' => 'ficticio', 'data' => []]);
    $queue->pushRaw($payload, 'runtime-probe');
    $job = $queue->pop('runtime-probe');
    expect($job)->not->toBeNull();
    $job->release(0);
    $queue->pop('runtime-probe')->delete();
    expect($queue->size('runtime-probe'))->toBe(0);
    $failed = app('queue.failer');
    $id = $failed->log('database', 'runtime-probe', $payload, new RuntimeException('Fallo sintético'));
    expect($failed->find($id))->not->toBeNull();
    expect($failed->forget($id))->toBeTrue();
    $batch = Bus::batch([])->name('Lote ficticio')->dispatch();
    $batch->cancel();
    expect(app(BatchRepository::class)->find($batch->id)->cancelled())->toBeTrue();
    app(BatchRepository::class)->delete($batch->id);
    expect(app(BatchRepository::class)->find($batch->id))->toBeNull();
});

test('cuenta restringida carece de privilegios globales y de esquema', function () {
    $pdo = $this->root->getPdo();
    $grantee = "'".$this->account."'@'127.0.0.1'";
    $query = $pdo->prepare("SELECT COUNT(*) FROM information_schema.USER_PRIVILEGES WHERE GRANTEE=? AND PRIVILEGE_TYPE<>'USAGE'");
    $query->execute([$grantee]);
    expect((int) $query->fetchColumn())->toBe(0);
    $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE=?');
    $query->execute([$grantee]);
    expect((int) $query->fetchColumn())->toBe(0);
    $query = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE=? AND IS_GRANTABLE='YES'");
    $query->execute([$grantee]);
    expect((int) $query->fetchColumn())->toBe(0);
});

test('transición y recuperación conservan Laravel al rotar la identidad administrativa ficticia', function () {
    $root = $this->root->getPdo();
    $account = 'rs_control_'.bin2hex(random_bytes(6));
    $old = bin2hex(random_bytes(32));
    $new = bin2hex(random_bytes(32));
    $identity = $root->quote($account)."@'127.0.0.1'";
    $root->exec('CREATE USER '.$identity.' IDENTIFIED VIA mysql_native_password USING PASSWORD('.$root->quote($old).') OR unix_socket');
    $root->exec('GRANT ALL PRIVILEGES ON *.* TO '.$identity.' WITH GRANT OPTION');
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    $dsn = 'mysql:host=127.0.0.1;port='.getenv('SPRINT4_TEST_PORT').';dbname='.$this->database;
    $control = new PDO($dsn, $account, $old, $options);
    $query = $root->prepare('SELECT Priv FROM mysql.global_priv WHERE User=? AND Host=?');
    $query->execute([$account, '127.0.0.1']);
    $before = json_decode($query->fetchColumn(), true);
    $usersBefore = $root->query('SELECT * FROM usuarios_sistema ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

    // Configuración recuperable ficticia: nunca lee ni escribe el .env operativo.
    $configuration = config('database.connections.runtime_probe');
    $path = dirname(getenv('SPRINT4_TEST_SOCKET')).'/recovery-'.bin2hex(random_bytes(6)).'.json';
    $stream = fopen($path, 'x');
    chmod($path, 0600);
    fwrite($stream, json_encode($configuration));
    fflush($stream);
    fsync($stream);
    fclose($stream);
    expect(fileperms($path) & 0777)->toBe(0600);

    $control->exec('SET SESSION sql_log_off=1');
    $control->exec('SET SESSION slow_query_log=0');
    $control->exec('SET PASSWORD = PASSWORD('.$control->quote($new).')');
    $denied = false;
    try {
        new PDO($dsn, $account, $old, $options);
    } catch (PDOException $exception) {
        $denied = in_array($exception->errorInfo[1] ?? null, [1045, 1698], true);
    }
    expect($denied)->toBeTrue()
        ->and((int) (new PDO($dsn, $account, $new, $options))->query('SELECT 1')->fetchColumn())->toBe(1)
        ->and((int) $control->query('SELECT 1')->fetchColumn())->toBe(1);

    // Otra conexión Laravel tras el cambio administrativo sigue usando su propia cuenta.
    DB::purge('runtime_probe');
    expect(Producto::count())->toBe(3);
    $this->post('/login', ['username' => $this->fixtures['usuario']->username, 'password' => 'clave-ficticia-sprint4'])
        ->assertRedirect('/dashboard');
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->get('/dashboard')->assertOk();

    // Simular configuración candidata incorrecta y recuperar desde el archivo privado.
    config(['database.connections.runtime_probe.password' => bin2hex(random_bytes(32))]);
    DB::purge('runtime_probe');
    $connectionFailed = false;
    try {
        DB::connection()->getPdo();
    } catch (PDOException $exception) {
        $connectionFailed = in_array($exception->errorInfo[1] ?? null, [1045, 1698], true);
    }
    expect($connectionFailed)->toBeTrue();
    $recovered = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    config(['database.connections.runtime_probe' => $recovered]);
    DB::purge('runtime_probe');
    expect(Producto::count())->toBe(3);
    $query->execute([$account, '127.0.0.1']);
    $after = json_decode($query->fetchColumn(), true);
    $plugins = fn (array $privileges) => [$privileges['plugin'], array_column($privileges['auth_or'], 'plugin')];
    expect($plugins($before))->toBe($plugins($after))
        ->and($root->query('SELECT * FROM usuarios_sistema ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $usersBefore)->toBeTrue();
});

test('cuenta restringida rechaza operaciones fuera del contrato', function (string $sql) {
    $sql = str_replace('{account}', $this->account, $sql);
    $error = null;
    try {
        DB::connection()->getPdo()->exec($sql);
    } catch (PDOException $exception) {
        $error = $exception->errorInfo[1] ?? null;
    }
    expect($error)->toBeIn([1044, 1142, 1143, 1227]);
})->with([
    'crear tabla' => ['CREATE TABLE denied_probe (id INT)'],
    'alterar tabla' => ['ALTER TABLE productos ADD denied_column INT'],
    'borrar tabla' => ['DROP TABLE productos'],
    'truncar tabla' => ['TRUNCATE TABLE productos'],
    'leer cuentas MariaDB' => ['SELECT User FROM mysql.user'],
    'leer migraciones' => ['SELECT * FROM migrations'],
    'leer pedidos sin módulo' => ['SELECT * FROM pedidos'],
    'modificar catálogo' => ["UPDATE roles SET nombre_roles='denied'"],
    'borrar empleado' => ['DELETE FROM empleados'],
    'crear cuenta' => ["CREATE USER 'denied_fixture'@'127.0.0.1'"],
    'delegar privilegios' => ["GRANT SELECT ON categorias TO '{account}'@'127.0.0.1'"],
    'crear otro esquema' => ['CREATE DATABASE denied_fixture_schema'],
    'administrar servidor' => ['SET GLOBAL max_connections=151'],
]);

test('identidad separada migra un esquema vacío sin privilegios globales', function () {
    $pdo = $this->root->getPdo();
    $database = 'rs-migration-'.bin2hex(random_bytes(6));
    $account = 'rs_migrate_'.bin2hex(random_bytes(6));
    $password = bin2hex(random_bytes(32));
    $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $identity = $pdo->quote($account)."@'127.0.0.1'";
    $pdo->exec('CREATE USER '.$identity.' IDENTIFIED BY '.$pdo->quote($password));
    $pdo->exec('GRANT '.implode(', ', MariaDbRuntimePrivileges::migrationPrivileges()).' ON `'.$database.'`.* TO '.$identity);
    config(['database.connections.migration_probe' => array_replace(config('database.connections.runtime_probe'), [
        'database' => $database, 'username' => $account, 'password' => $password,
    ]), 'database.default' => 'migration_probe']);
    try {
        expect(Artisan::call('migrate', ['--database' => 'migration_probe', '--force' => true]))->toBe(0);
        expect(DB::table('migrations')->count())->toBe(count(glob(database_path('migrations/*.php'))));
        DB::statement('CREATE TABLE ddl_probe (id INT PRIMARY KEY)');
        DB::statement('ALTER TABLE ddl_probe ADD value INT');
        DB::statement('CREATE INDEX ddl_probe_index ON ddl_probe (value)');
        DB::statement('DROP TABLE ddl_probe');
        $denied = false;
        try {
            DB::connection()->getPdo()->query('SELECT User FROM mysql.user');
        } catch (PDOException $exception) {
            $denied = ($exception->errorInfo[1] ?? null) === 1142;
        }
        expect($denied)->toBeTrue();
    } finally {
        DB::disconnect('migration_probe');
        config(['database.default' => 'runtime_probe']);
    }
});
