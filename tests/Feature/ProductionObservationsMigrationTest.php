<?php

use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use Database\Seeders\UnidadMedidaSeeder;
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
    $horaPanPath = database_path('migrations/2026_10_11_100000_add_hora_finalizacion_to_detalle_pan.php');
    $codigoPath = database_path('migrations/2026_10_15_100000_add_codigo_to_unidades_medida.php');
    $originals = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_02'));
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

    // Cantidad de Torta evoluciona el contrato histórico: una fila física = 1.
    $tortaAntes = (array) DB::table('detalle_torta')->where('id', $before['detalle_torta']->id)->first();
    $cantidadesTorta = require database_path('migrations/2026_10_10_120000_add_cantidad_to_detalle_torta.php');
    $cantidadesTorta->up();
    $tortaDespues = (array) DB::table('detalle_torta')->where('id', $tortaAntes['id'])->first();
    expect($tortaDespues['cantidad'])->toBe(1);
    unset($tortaDespues['cantidad']);
    expect($tortaDespues)->toBe($tortaAntes);
    $columna = collect(Schema::getColumns('detalle_torta'))->firstWhere('name', 'cantidad');
    expect($columna['type_name'])->toBe('int')->and($columna['nullable'])->toBeFalse()
        ->and($columna['default'])->toBeNull();
    foreach ([0, -1] as $cantidad) {
        expect(fn () => DB::table('detalle_torta')->where('id', $tortaAntes['id'])->update(['cantidad' => $cantidad]))
            ->toThrow(QueryException::class);
    }
    expect(fn () => DB::table('detalle_torta')->insert([
        'produccion_id' => $tortaAntes['produccion_id'], 'producto_id' => $tortaAntes['producto_id'], 'forma' => 'circular',
    ]))->toThrow(QueryException::class);
    DB::table('detalle_torta')->where('id', $tortaAntes['id'])->update(['cantidad' => 4]);
    expect(fn () => $cantidadesTorta->down())->toThrow(RuntimeException::class, 'distintas de uno');
    expect(DB::table('detalle_torta')->where('id', $tortaAntes['id'])->value('cantidad'))->toBe(4);
    DB::table('detalle_torta')->where('id', $tortaAntes['id'])->update(['cantidad' => 1]);
    $cantidadesTorta->down();
    expect(Schema::hasColumn('detalle_torta', 'cantidad'))->toBeFalse()
        ->and((array) DB::table('detalle_torta')->where('id', $tortaAntes['id'])->first())->toBe($tortaAntes);
    $cantidadesTorta->up();
    expect(DB::table('detalle_torta')->where('id', $tortaAntes['id'])->value('cantidad'))->toBe(1);

    // La hora no se infiere para registros históricos. Ensayo DDL solo en aislamiento.
    $panAntes = (array) DB::table('detalle_pan')->where('id', $before['detalle_pan']->id)->first();
    $horaPan = require $horaPanPath;
    $horaPan->up();
    $panDespues = (array) DB::table('detalle_pan')->where('id', $panAntes['id'])->first();
    expect($panDespues['hora_finalizacion'])->toBeNull();
    unset($panDespues['hora_finalizacion']);
    expect($panDespues)->toBe($panAntes);
    $columnaHora = collect(Schema::getColumns('detalle_pan'))->firstWhere('name', 'hora_finalizacion');
    expect($columnaHora['type_name'])->toBe('time')
        ->and($columnaHora['nullable'])->toBeTrue()
        ->and(Schema::hasColumn('produccion', 'hora_finalizacion'))->toBeFalse();
    DB::table('detalle_pan')->where('id', $panAntes['id'])->update(['hora_finalizacion' => '08:35']);
    expect(fn () => $horaPan->down())->toThrow(RuntimeException::class, 'hay horas de finalización');
    expect(DB::table('detalle_pan')->where('id', $panAntes['id'])->value('hora_finalizacion'))->toBe('08:35:00');
    DB::table('detalle_pan')->where('id', $panAntes['id'])->update(['hora_finalizacion' => null]);
    $horaPan->down();
    expect(Schema::hasColumn('detalle_pan', 'hora_finalizacion'))->toBeFalse()
        ->and((array) DB::table('detalle_pan')->where('id', $panAntes['id'])->first())->toBe($panAntes);
    $horaPan->up();
    expect(DB::table('detalle_pan')->where('id', $panAntes['id'])->value('hora_finalizacion'))->toBeNull();

    // Registrador por lote: preservar históricos, FK y reversión protegida.
    $migration = require database_path('migrations/2026_10_11_120000_add_registrador_to_production_details.php');
    $tablas = ['detalle_pan', 'detalle_torta', 'detalle_bocadito'];
    $antes = [];
    foreach ($tablas as $tabla) {
        $antes[$tabla] = DB::table($tabla)->get()->map(fn ($fila) => (array) $fila)->all();
    }
    $migration->up();
    foreach ($tablas as $tabla) {
        $columna = collect(Schema::getColumns($tabla))->firstWhere('name', 'registrado_por_usuario_id');
        expect($columna['nullable'])->toBeTrue();
        $despues = DB::table($tabla)->get()->map(function ($fila) {
            $datos = (array) $fila;
            expect($datos['registrado_por_usuario_id'])->toBeNull();
            unset($datos['registrado_por_usuario_id']);

            return $datos;
        })->all();
        expect($despues)->toBe($antes[$tabla]);
        expect(fn () => DB::table($tabla)->update(['registrado_por_usuario_id' => 999999]))->toThrow(QueryException::class);
    }
    $usuarioId = DB::table('usuarios_sistema')->value('id');
    DB::table('detalle_torta')->update(['registrado_por_usuario_id' => $usuarioId]);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'hay registradores');
    foreach ($tablas as $tabla) {
        expect(Schema::hasColumn($tabla, 'registrado_por_usuario_id'))->toBeTrue();
    }
    DB::table('detalle_torta')->update(['registrado_por_usuario_id' => null]);
    $migration->down();
    foreach ($tablas as $tabla) {
        expect(Schema::hasColumn($tabla, 'registrado_por_usuario_id'))->toBeFalse()
            ->and(DB::table($tabla)->get()->map(fn ($fila) => (array) $fila)->all())->toBe($antes[$tabla]);
    }
    $migration->up();

    // Identidad canónica aditiva sobre unidades/productos/detalles ya existentes.
    $tablasCanonicas = ['unidades_medida', 'productos', 'detalle_pan', 'detalle_torta', 'detalle_bocadito'];
    $filasLegacy = [];
    foreach ($tablasCanonicas as $tabla) {
        $filasLegacy[$tabla] = DB::table($tabla)->orderBy('id')->get()->toJson();
    }
    $codigoMigration = require $codigoPath;
    $codigoMigration->up();
    expect(DB::table('unidades_medida')->whereNotNull('codigo')->count())->toBe(0);
    foreach ($tablasCanonicas as $tabla) {
        $filas = DB::table($tabla)->orderBy('id')->get();
        if ($tabla === 'unidades_medida') {
            $filas->each(function ($fila) {
                unset($fila->codigo);
            });
        }
        expect($filas->toJson())->toBe($filasLegacy[$tabla]);
    }
    (new UnidadMedidaSeeder)->run();
    expect(fn () => $codigoMigration->down())->toThrow(RuntimeException::class, 'identidades canónicas');
    expect(Schema::hasColumn('unidades_medida', 'codigo'))->toBeTrue();
    // Borrar únicamente códigos ficticios para ensayar reversión sin datos canónicos.
    DB::table('unidades_medida')->update(['codigo' => null]);
    $codigoMigration->down();
    expect(Schema::hasColumn('unidades_medida', 'codigo'))->toBeFalse();
    $codigoMigration->up();
    (new UnidadMedidaSeeder)->run();

    // Hora de vitrina aditiva: ninguna fila previa se reinterpreta ni se rellena.
    $tablasVitrina = ['produccion', 'productos', 'detalle_pan', 'detalle_torta', 'detalle_bocadito',
        'detalle_pan_empleado', 'detalle_torta_empleado', 'detalle_bocadito_empleado'];
    $filasAntesVitrina = $esquemasAntesVitrina = [];
    foreach ($tablasVitrina as $tabla) {
        $filasAntesVitrina[$tabla] = DB::table($tabla)->get()->map(fn ($fila) => (array) $fila)->all();
        $esquemasAntesVitrina[$tabla] = Schema::getColumns($tabla);
    }
    $horaVitrina = require database_path('migrations/2026_10_21_100000_add_hora_vitrina_to_detalle_torta.php');
    $horaVitrina->up();
    $columnaVitrina = collect(Schema::getColumns('detalle_torta'))->firstWhere('name', 'hora_vitrina');
    expect($columnaVitrina['type_name'])->toBe('time')->and($columnaVitrina['nullable'])->toBeTrue()
        ->and($columnaVitrina['default'])->toBeIn([null, 'NULL']);
    foreach ($tablasVitrina as $tabla) {
        $filas = DB::table($tabla)->get()->map(function ($fila) use ($tabla) {
            $datos = (array) $fila;
            if ($tabla === 'detalle_torta') {
                expect($datos['hora_vitrina'])->toBeNull();
                unset($datos['hora_vitrina']);
            }

            return $datos;
        })->all();
        expect($filas)->toBe($filasAntesVitrina[$tabla])
            ->and(collect(Schema::getColumns($tabla))->reject(fn ($columna) => $columna['name'] === 'hora_vitrina')->values()->all())
            ->toBe($esquemasAntesVitrina[$tabla]);
    }
    $idTorta = $filasAntesVitrina['detalle_torta'][0]['id'];
    DB::table('detalle_torta')->where('id', $idTorta)->update(['hora_vitrina' => '14:15']);
    expect(fn () => $horaVitrina->down())->toThrow(RuntimeException::class, 'hay horas de vitrina')
        ->and(DB::table('detalle_torta')->where('id', $idTorta)->value('hora_vitrina'))->toBe('14:15:00');
    // Retirar exclusivamente la hora ficticia para comprobar down y reaplicación.
    DB::table('detalle_torta')->where('id', $idTorta)->update(['hora_vitrina' => null]);
    $horaVitrina->down();
    expect(Schema::getColumns('detalle_torta'))->toBe($esquemasAntesVitrina['detalle_torta'])
        ->and(DB::table('detalle_torta')->get()->map(fn ($fila) => (array) $fila)->all())->toBe($filasAntesVitrina['detalle_torta']);
    $horaVitrina->up();
    expect(DB::table('detalle_torta')->where('id', $idTorta)->value('hora_vitrina'))->toBeNull();
});
