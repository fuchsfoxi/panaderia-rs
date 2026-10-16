<?php

use App\Models\Cargo;
use App\Models\Empleado;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\EmpleadoSeeder;
use Database\Seeders\RolSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para probar seeders en MariaDB aislada.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    config(['auth.initial_admin_password' => Str::random(32)]);
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

test('RolSeeder prepara solo los dos roles funcionales y repetirlo conserva sus IDs', function () {
    $this->seed(RolSeeder::class);
    $iniciales = Rol::orderBy('nombre_roles')->pluck('id', 'nombre_roles')->all();
    $this->seed(RolSeeder::class);
    expect(array_keys($iniciales))->toBe([Rol::ADMINISTRADOR, Rol::OPERADOR])
        ->and(Rol::count())->toBe(2)
        ->and(Rol::orderBy('nombre_roles')->pluck('id', 'nombre_roles')->all())->toBe($iniciales);
});

test('RolSeeder conserva Encargado y los roles existentes con IDs arbitrarios', function () {
    DB::table('roles')->insert([
        ['id' => 1, 'nombre_roles' => Rol::OPERADOR],
        ['id' => 43, 'nombre_roles' => Rol::ADMINISTRADOR],
        ['id' => 97, 'nombre_roles' => 'Encargado'],
    ]);
    $iniciales = Rol::orderBy('id')->get()->toArray();
    $this->seed(RolSeeder::class);
    $this->seed(RolSeeder::class);
    expect(Rol::orderBy('id')->get()->toArray())->toBe($iniciales);
});

test('EmpleadoSeeder resuelve cargos por nombre y no duplica empleados al repetirse', function () {
    DB::table('cargos')->insert([
        ['id' => 71, 'nombre_cargos' => 'Administrador'],
        ['id' => 19, 'nombre_cargos' => 'Panadero'],
    ]);
    $this->seed(EmpleadoSeeder::class);
    $iniciales = Empleado::orderBy('numero_empleados')->get()->toArray();
    $this->seed(EmpleadoSeeder::class);
    expect(Empleado::count())->toBe(2)
        ->and(Empleado::orderBy('numero_empleados')->get()->toArray())->toBe($iniciales)
        ->and(Empleado::where('numero_empleados', '001')->sole()->cargo_id)->toBe(71)
        ->and(Empleado::where('numero_empleados', '002')->sole()->cargo_id)->toBe(19);
});

test('UsuarioSeeder resuelve rol y empleado por identidad y preserva la cuenta al repetirse', function () {
    DB::table('roles')->insert([
        ['id' => 1, 'nombre_roles' => Rol::OPERADOR],
        ['id' => 85, 'nombre_roles' => Rol::ADMINISTRADOR],
    ]);
    $cargo = Cargo::create(['nombre_cargos' => 'Cargo ficticio']);
    DB::table('empleados')->insert([
        ['id' => 1, 'nombre_empleados' => 'Otro empleado', 'numero_empleados' => '999', 'cargo_id' => $cargo->id],
        ['id' => 63, 'nombre_empleados' => EmpleadoSeeder::NOMBRE_ADMINISTRADOR, 'numero_empleados' => EmpleadoSeeder::NUMERO_ADMINISTRADOR, 'cargo_id' => $cargo->id],
    ]);
    $this->seed(UsuarioSeeder::class);
    $usuario = Usuario::where('username', 'admin')->sole();
    expect($usuario->rol_id)->toBe(85)->and($usuario->empleado_id)->toBe(63)
        ->and(Hash::check(config('auth.initial_admin_password'), $usuario->password_hash))->toBeTrue();
    $anterior = $usuario->getRawOriginal();
    config(['auth.initial_admin_password' => Str::random(32)]);
    $this->seed(UsuarioSeeder::class);
    expect(Usuario::count())->toBe(1)
        ->and($usuario->fresh()->getRawOriginal())->toBe($anterior);

    // Sembrar no vuelve a promover ni a revincular una cuenta ya administrada.
    $usuario->update(['rol_id' => 1, 'empleado_id' => 1]);
    config(['auth.initial_admin_password' => null]);
    $this->seed(UsuarioSeeder::class);
    expect($usuario->fresh()->rol_id)->toBe(1)->and($usuario->fresh()->empleado_id)->toBe(1)
        ->and($usuario->fresh()->password_hash)->toBe($anterior['password_hash']);
});

test('UsuarioSeeder rechaza una identidad de rol ambigua sin crear cuentas', function () {
    Rol::create(['nombre_roles' => Rol::ADMINISTRADOR]);
    Rol::create(['nombre_roles' => Rol::ADMINISTRADOR]);
    expect(fn () => $this->seed(UsuarioSeeder::class))->toThrow(MultipleRecordsFoundException::class);
    expect(Usuario::count())->toBe(0);
});

test('UsuarioSeeder sin credencial segura no crea ninguna cuenta ni imprime secretos', function (mixed $password) {
    config(['auth.initial_admin_password' => $password]);
    // Debe fallar antes de resolver relaciones o crear el usuario.
    expect(fn () => $this->seed(UsuarioSeeder::class))->toThrow(
        RuntimeException::class,
        'Configura INITIAL_ADMIN_PASSWORD con al menos 16 caracteres y un máximo de 72 bytes para crear el administrador inicial.',
    );
    expect(Usuario::count())->toBe(0);
})->with([
    'ausente' => null,
    'vacía' => '',
    'corta' => 'ficticia',
    'espacios' => str_repeat(' ', 20),
    'exceso bcrypt' => str_repeat('x', 73),
    'multibyte exceso bcrypt' => str_repeat('á', 37),
    'tipo incorrecto' => [['ficticio']],
]);
