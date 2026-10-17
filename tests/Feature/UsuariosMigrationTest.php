<?php

use App\Models\Cargo;
use App\Models\Empleado;
use App\Models\Rol;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\IsolatedMariaDb;

test('migración de cuentas conserva filas FK UNIQUE y activa cuentas existentes en MariaDB', function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    IsolatedMariaDb::connect('sprint4_migration_test');
    $path = database_path('migrations/2026_10_16_100000_add_access_fields_to_usuarios_sistema.php');
    $anteriores = array_values(array_diff(glob(database_path('migrations/*.php')), [$path]));
    expect(IsolatedMariaDb::migrateInNewDatabase($anteriores))->toBe(0);
    try {
        $cargo = Cargo::create(['nombre_cargos' => 'Cargo de migración ficticio']);
        $empleado = Empleado::create(['nombre_empleados' => 'Persona ficticia', 'cargo_id' => $cargo->id]);
        $rol = Rol::create(['nombre_roles' => Rol::ADMINISTRADOR]);
        $fila = ['username' => 'Admin.Legacy', 'password_hash' => Hash::make(Str::random(32)), 'rol_id' => $rol->id,
            'empleado_id' => $empleado->id, 'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-01 08:00:00'];
        $id = DB::table('usuarios_sistema')->insertGetId($fila);
        $antes = (array) DB::table('usuarios_sistema')->find($id);
        $foreignAntes = Schema::getForeignKeys('usuarios_sistema');
        $indexesAntes = Schema::getIndexes('usuarios_sistema');
        expect(collect(Schema::getColumns('usuarios_sistema'))->firstWhere('name', 'empleado_id')['nullable'])->toBeFalse();
        $migration = require $path;
        $migration->up();
        $despues = (array) DB::table('usuarios_sistema')->find($id);
        expect((bool) $despues['activo'])->toBeTrue()->and($despues['turno_id'])->toBeNull();
        unset($despues['activo'], $despues['turno_id']);
        expect($despues)->toBe($antes);
        $columns = collect(Schema::getColumns('usuarios_sistema'));
        expect($columns->firstWhere('name', 'empleado_id')['nullable'])->toBeTrue()
            ->and($columns->firstWhere('name', 'turno_id')['nullable'])->toBeTrue()
            ->and((string) $columns->firstWhere('name', 'activo')['default'])->toBe('1');
        $foreign = collect(Schema::getForeignKeys('usuarios_sistema'));
        foreach ($foreignAntes as $fk) {
            expect($foreign->firstWhere('name', $fk['name']))->toBe($fk);
        }
        expect($foreign->firstWhere('columns', ['turno_id'])['foreign_table'])->toBe('turnos');
        foreach ($indexesAntes as $index) {
            expect(collect(Schema::getIndexes('usuarios_sistema'))->firstWhere('name', $index['name']))->toBe($index);
        }
        foreach (['compartida_1', 'compartida_2'] as $username) {
            DB::table('usuarios_sistema')->insert(array_replace($fila, ['username' => $username, 'empleado_id' => null]));
        }
        expect(DB::table('usuarios_sistema')->whereNull('empleado_id')->count())->toBe(2);
        foreach ([
            ['username' => 'duplicado_empleado'],
            ['username' => 'empleado_inexistente', 'empleado_id' => $empleado->id + 100000],
            ['username' => 'turno_inexistente', 'empleado_id' => null, 'turno_id' => 999999],
        ] as $cambios) {
            expect(fn () => DB::table('usuarios_sistema')->insert(array_replace($fila, $cambios)))->toThrow(QueryException::class);
        }
        expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'cuentas compartidas');
        expect(Schema::hasColumn('usuarios_sistema', 'activo'))->toBeTrue();
        DB::table('usuarios_sistema')->whereNull('empleado_id')->delete();
        DB::table('usuarios_sistema')->where('id', $id)->update(['activo' => false]);
        expect(fn () => $migration->down())->toThrow(RuntimeException::class);
        DB::table('usuarios_sistema')->where('id', $id)->update(['activo' => true]);
        $migration->down();
        expect((array) DB::table('usuarios_sistema')->find($id))->toBe($antes)
            ->and(Schema::getForeignKeys('usuarios_sistema'))->toEqualCanonicalizing($foreignAntes)
            ->and(Schema::getIndexes('usuarios_sistema'))->toEqualCanonicalizing($indexesAntes);
    } finally {
        IsolatedMariaDb::restoreDatabase();
    }
});
