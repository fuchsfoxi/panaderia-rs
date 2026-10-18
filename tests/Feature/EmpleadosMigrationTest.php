<?php

use App\Models\DetallePan;
use App\Models\Produccion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedMariaDb;

test('migración de empleados conserva personas cuentas y pivotes y no inventa rol actual', function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    $path = database_path('migrations/2026_10_18_100000_add_rol_actual_and_activo_to_empleados.php');
    $anteriores = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($migration) => basename($migration) < basename($path)));
    expect(IsolatedMariaDb::migrateInNewDatabase($anteriores))->toBe(0);
    try {
        $fixtures = IsolatedMariaDb::fixtures();
        $cabecera = Produccion::create(['fecha' => '2026-10-18', 'registrado_por_usuario_id' => $fixtures['usuario']->id,
            'categoria_id' => $fixtures['productos']['Pan']->categoria_id, 'turno_id' => $fixtures['turno']->id]);
        $detalle = IsolatedMariaDb::detail(DetallePan::class, $cabecera, $fixtures);
        $detalle->empleados()->attach($fixtures['empleados'][0]->id, ['rol_produccion_id' => $fixtures['roles']['Maestro']->id]);
        $antes = [];
        foreach (['empleados', 'usuarios_sistema', 'detalle_pan_empleado', 'roles_produccion', 'cargos'] as $tabla) {
            $antes[$tabla] = DB::table($tabla)->get()->map(fn ($row) => (array) $row)->all();
        }
        $foreign = Schema::getForeignKeys('empleados');
        $migration = require $path;
        $migration->up();
        $columnas = collect(Schema::getColumns('empleados'));
        expect($columnas->firstWhere('name', 'cargo_id')['nullable'])->toBeTrue()
            ->and($columnas->firstWhere('name', 'rol_produccion_id')['nullable'])->toBeTrue()
            ->and((string) $columnas->firstWhere('name', 'activo')['default'])->toBe('1');
        $filas = DB::table('empleados')->get()->map(function ($row) {
            expect($row->rol_produccion_id)->toBeNull()->and((bool) $row->activo)->toBeTrue();
            unset($row->rol_produccion_id, $row->activo);

            return (array) $row;
        })->all();
        expect($filas)->toBe($antes['empleados']);
        foreach (['usuarios_sistema', 'detalle_pan_empleado', 'roles_produccion', 'cargos'] as $tabla) {
            expect(DB::table($tabla)->get()->map(fn ($row) => (array) $row)->all())->toBe($antes[$tabla]);
        }
        expect(collect(Schema::getForeignKeys('empleados'))->firstWhere('columns', ['cargo_id']))->toBe($foreign[0]);
        expect(fn () => DB::table('empleados')->where('id', $fixtures['empleados'][0]->id)->update(['rol_produccion_id' => 999999]))->toThrow(QueryException::class);
        DB::table('empleados')->where('id', $fixtures['empleados'][0]->id)->update(['rol_produccion_id' => $fixtures['roles']['Ayudante']->id]);
        expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'deben conservarse');
        expect(DB::table('detalle_pan_empleado')->value('rol_produccion_id'))->toBe($fixtures['roles']['Maestro']->id);
        DB::table('empleados')->update(['rol_produccion_id' => null, 'activo' => true]);
        DB::table('empleados')->where('id', $fixtures['empleados'][0]->id)->update(['activo' => false]);
        expect(fn () => $migration->down())->toThrow(RuntimeException::class);
        DB::table('empleados')->update(['activo' => true]);
        $migration->down();
        foreach ($antes as $tabla => $filasAntes) {
            expect(DB::table($tabla)->get()->map(fn ($row) => (array) $row)->all())->toBe($filasAntes);
        }
        expect(Schema::getForeignKeys('empleados'))->toBe($foreign);
        $migration->up();
        $id = DB::table('empleados')->insertGetId(['nombre_empleados' => 'Nuevo ficticio sin cargo', 'cargo_id' => null]);
        expect(fn () => $migration->down())->toThrow(RuntimeException::class);
        expect(DB::table('empleados')->where('id', $id)->exists())->toBeTrue();
    } finally {
        IsolatedMariaDb::restoreDatabase();
    }
});
