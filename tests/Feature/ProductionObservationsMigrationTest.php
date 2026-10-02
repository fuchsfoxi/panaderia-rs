<?php

use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedMariaDb;

test('las migraciones aditivas preservan registros y se revierten solo en la base aislada', function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para probar la evolución del esquema.');
    }
    IsolatedMariaDb::connect('sprint4_migration_test');
    $new = database_path('migrations/2026_10_02_162600_add_observacion_to_production_details.php');
    $familyPath = database_path('migrations/2026_10_02_180000_add_categoria_and_turno_to_produccion.php');
    $panUnitsPath = database_path('migrations/2026_10_02_200000_add_panes_por_lata_to_productos_and_detalle_pan.php');
    $originals = array_values(array_diff(glob(database_path('migrations/*.php')), [$new, $familyPath, $panUnitsPath]));
    expect($originals)->toHaveCount(21);
    expect(Artisan::call('migrate', [
        '--database' => 'sprint4_test', '--path' => $originals, '--realpath' => true, '--force' => true,
    ]))->toBe(0);
    $fixtures = IsolatedMariaDb::fixtures();
    $before = [];
    foreach ([DetallePan::class, DetalleTorta::class, DetalleBocadito::class] as $class) {
        $produccion = Produccion::create(['fecha' => '2026-10-03', 'registrado_por_usuario_id' => $fixtures['usuario']->id]);
        $detail = IsolatedMariaDb::detail($class, $produccion, $fixtures);
        $before[$detail->getTable()] = DB::table($detail->getTable())->where('id', $detail->id)->first();
    }
    $migration = require $new;
    $migration->up();
    foreach ($before as $table => $row) {
        $after = (array) DB::table($table)->where('id', $row->id)->first();
        expect($after['observacion'])->toBeNull();
        unset($after['observacion']);
        expect($after)->toBe((array) $row);
        DB::table($table)->where('id', $row->id)->update(['observacion' => 'Texto de prueba']);
    }
    expect(Schema::hasColumn('produccion', 'observacion'))->toBeFalse()
        ->and(Schema::hasColumn('detalle_torta', 'cantidad'))->toBeFalse();
    $panQuantity = collect(Schema::getColumns('detalle_pan'))->firstWhere('name', 'cantidad');
    expect($panQuantity['type_name'])->toBe('int');

    // Solo la segunda tabla conserva texto: el preflight debe impedir que se
    // elimine primero la columna de Pan antes de encontrar la de Torta.
    foreach (['detalle_pan', 'detalle_bocadito'] as $table) {
        DB::table($table)->where('id', $before[$table]->id)->update(['observacion' => null]);
    }
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'hay observaciones');
    foreach ($before as $table => $row) {
        expect(Schema::hasColumn($table, 'observacion'))->toBeTrue();
        // Solo se borra texto ficticio para probar el rollback sin datos nuevos.
        DB::table($table)->where('id', $row->id)->update(['observacion' => null]);
    }

    // DDL fuera de una transacción: únicamente en el servidor desechable verificado.
    $migration->down();
    foreach ($before as $table => $row) {
        expect(Schema::hasColumn($table, 'observacion'))->toBeFalse()
            ->and((array) DB::table($table)->where('id', $row->id)->first())->toBe((array) $row);
    }
    $migration->up();
    foreach ($before as $table => $row) {
        expect(DB::table($table)->where('id', $row->id)->value('observacion'))->toBeNull();
    }

    $headers = DB::table('produccion')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    $legacyColumns = Schema::getColumns('detalle_pan');
    $legacyForeignKeys = Schema::getForeignKeys('detalle_pan');
    $familyMigration = require $familyPath;
    $familyMigration->up();
    foreach ($headers as $row) {
        $after = (array) DB::table('produccion')->where('id', $row['id'])->first();
        expect($after['categoria_id'])->toBeNull()->and($after['turno_id'])->toBeNull();
        unset($after['categoria_id'], $after['turno_id']);
        expect($after)->toBe($row);
    }
    $columns = collect(Schema::getColumns('produccion'));
    $foreignKeys = collect(Schema::getForeignKeys('produccion'));
    foreach (['categoria_id' => 'categorias', 'turno_id' => 'turnos'] as $column => $target) {
        expect($columns->firstWhere('name', $column)['nullable'])->toBeTrue();
        $fk = $foreignKeys->first(fn ($fk) => $fk['columns'] === [$column]);
        expect($fk['foreign_table'])->toBe($target)
            ->and($fk['foreign_columns'])->toBe(['id'])
            ->and($fk['on_delete'])->toBe('restrict');
        // Referencia inexistente calculada, sin identidad por ID fijo.
        $missingId = DB::table($target)->max('id') + 1000;
        try {
            DB::table('produccion')->where('id', $headers[0]['id'])->update([$column => $missingId]);
            $this->fail('Se permitió una referencia inexistente.');
        } catch (QueryException $exception) {
            expect($exception->errorInfo[1])->toBe(1452);
        }
        $validId = $column === 'categoria_id' ? $fixtures['productos']['Pan']->categoria_id : $fixtures['turno']->id;
        DB::table('produccion')->where('id', $headers[0]['id'])->update([$column => $validId]);
        expect(fn () => $familyMigration->down())->toThrow(RuntimeException::class, 'cabecera asignados');
        expect(Schema::hasColumn('produccion', 'categoria_id'))->toBeTrue()
            ->and(Schema::hasColumn('produccion', 'turno_id'))->toBeTrue()
            ->and(DB::table('produccion')->where('id', $headers[0]['id'])->value($column))->toBe($validId);
        // Solo se retira una asignación ficticia para ensayar down().
        DB::table('produccion')->where('id', $headers[0]['id'])->update([$column => null]);
    }
    expect(Schema::getColumns('detalle_pan'))->toBe($legacyColumns)
        ->and(Schema::getForeignKeys('detalle_pan'))->toBe($legacyForeignKeys);
    $familyMigration->down();
    expect(Schema::hasColumn('produccion', 'categoria_id'))->toBeFalse()
        ->and(Schema::hasColumn('produccion', 'turno_id'))->toBeFalse()
        ->and(DB::table('produccion')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all())->toBe($headers);
    foreach ($before as $table => $row) {
        $after = (array) DB::table($table)->where('id', $row->id)->first();
        unset($after['observacion']);
        expect($after)->toBe((array) $row);
    }
    $familyMigration->up();
    expect(DB::table('produccion')->whereNotNull('categoria_id')->orWhereNotNull('turno_id')->count())->toBe(0)
        ->and(Schema::getColumns('detalle_pan'))->toBe($legacyColumns)
        ->and(Schema::getForeignKeys('detalle_pan'))->toBe($legacyForeignKeys);

    // La tercera migración se ensaya sobre estos registros anteriores, sin
    // modificar las 21 originales ni afectar el servidor de trabajo.
    DB::table('detalle_pan_empleado')->insert([
        'detalle_pan_id' => $before['detalle_pan']->id,
        'empleado_id' => $fixtures['empleados']->first()->id,
        'rol_produccion_id' => $fixtures['roles']['Maestro']->id,
    ]);
    $rowsBefore = [];
    $schemaBefore = [];
    foreach (['productos', 'detalle_pan', 'produccion', 'detalle_torta', 'detalle_bocadito', 'detalle_pan_empleado'] as $table) {
        $rowsBefore[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        $schemaBefore[$table] = Schema::getColumns($table);
    }
    $fields = ['productos' => 'panes_por_lata', 'detalle_pan' => 'panes_por_lata_usado'];
    $panUnitsMigration = require $panUnitsPath;
    $panUnitsMigration->up();
    foreach ($rowsBefore as $table => $rows) {
        $field = $fields[$table] ?? null;
        $columns = collect(Schema::getColumns($table));
        if ($field) {
            $column = $columns->firstWhere('name', $field);
            expect($column['type_name'])->toBe('int')
                ->and($column['nullable'])->toBeTrue()
                ->and($column['default'])->toBeIn([null, 'NULL']); // Metadatos MariaDB: NULL SQL, sin valor inventado.
        }
        expect($columns->reject(fn ($column) => $column['name'] === $field)->values()->all())->toBe($schemaBefore[$table]);
        $after = DB::table($table)->get()->map(function ($row) use ($field) {
            $row = (array) $row;
            if ($field) {
                expect($row[$field])->toBeNull();
                unset($row[$field]);
            }

            return $row;
        })->all();
        expect($after)->toBe($rows);
    }
    expect(Schema::getForeignKeys('detalle_pan'))->toBe($legacyForeignKeys);

    // Cada campo por separado bloquea down() antes de perder cualquiera de
    // las columnas, incluso si el parámetro presente vale cero.
    foreach ($fields as $table => $field) {
        $id = $rowsBefore[$table][0]['id'];
        $value = $table === 'productos' ? 0 : 12;
        DB::table($table)->where('id', $id)->update([$field => $value]);
        expect(fn () => $panUnitsMigration->down())->toThrow(RuntimeException::class, 'hay parámetros o snapshots');
        foreach ($fields as $target => $column) {
            expect(Schema::hasColumn($target, $column))->toBeTrue();
        }
        expect(DB::table($table)->where('id', $id)->value($field))->toBe($value);
        // Retirar solo valores ficticios para probar la reversión protegida.
        DB::table($table)->where('id', $id)->update([$field => null]);
    }
    $panUnitsMigration->down();
    foreach ($rowsBefore as $table => $rows) {
        expect(Schema::getColumns($table))->toBe($schemaBefore[$table])
            ->and(DB::table($table)->get()->map(fn ($row) => (array) $row)->all())->toBe($rows);
    }
    $panUnitsMigration->up();
    foreach ($fields as $table => $field) {
        expect(Schema::hasColumn($table, $field))->toBeTrue()
            ->and(DB::table($table)->whereNotNull($field)->count())->toBe(0);
    }
    expect(Schema::getForeignKeys('detalle_pan'))->toBe($legacyForeignKeys);
});
