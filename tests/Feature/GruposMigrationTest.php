<?php

use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedMariaDb;

test('migración de grupos es aditiva y aplica FK restrictivas PK compuesta y rollback protegido', function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    $path = database_path('migrations/2026_10_21_110000_create_grupos_produccion_tables.php');
    $previas = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($file) => basename($file) < basename($path)));
    expect(IsolatedMariaDb::migrateInNewDatabase($previas))->toBe(0);
    try {
        $fixtures = IsolatedMariaDb::fixtures();
        $tablas = ['empleados', 'usuarios_sistema', 'categorias', 'roles_produccion', 'turnos', 'produccion'];
        foreach (['Pan' => DetallePan::class, 'Torta' => DetalleTorta::class, 'Bocadito' => DetalleBocadito::class] as $familia => $modelo) {
            $produccion = Produccion::create(['fecha' => '2026-10-21', 'categoria_id' => $fixtures['productos'][$familia]->categoria_id,
                'turno_id' => $fixtures['turno']->id, 'registrado_por_usuario_id' => $fixtures['usuario']->id]);
            $detalle = IsolatedMariaDb::detail($modelo, $produccion, $fixtures);
            $detalle->empleados()->attach($fixtures['empleados'][0]->id, ['rol_produccion_id' => $fixtures['roles']['Maestro']->id]);
            $tablas[] = $detalle->getTable();
            $tablas[] = $detalle->getTable().'_empleado';
        }
        $filas = $esquemas = [];
        foreach ($tablas as $tabla) {
            $filas[$tabla] = DB::table($tabla)->get()->toJson();
            $esquemas[$tabla] = Schema::getColumns($tabla);
        }
        $migration = require $path;
        $migration->up();
        foreach ($tablas as $tabla) {
            expect(DB::table($tabla)->get()->toJson())->toBe($filas[$tabla])
                ->and(Schema::getColumns($tabla))->toBe($esquemas[$tabla]);
        }
        expect(Schema::getColumnListing('grupo_integrantes'))->toBe(['grupo_id', 'empleado_id'])
            ->and(Schema::hasColumn('grupos_produccion', 'turno_id'))->toBeFalse();
        foreach (['grupos_produccion', 'grupo_integrantes'] as $tabla) {
            foreach (Schema::getForeignKeys($tabla) as $foreign) {
                expect($foreign['on_delete'])->toBe('restrict')->and($foreign['on_update'])->toBe('restrict');
            }
        }
        $grupo = ['nombre' => 'Equipo ficticio', 'categoria_id' => $fixtures['productos']['Pan']->categoria_id,
            'maestro_id' => $fixtures['empleados'][0]->id];
        $id = DB::table('grupos_produccion')->insertGetId($grupo);
        $idOtro = DB::table('grupos_produccion')->insertGetId($grupo);
        expect((bool) DB::table('grupos_produccion')->where('id', $id)->value('activo'))->toBeTrue();
        $pivot = ['grupo_id' => $id, 'empleado_id' => $fixtures['empleados'][1]->id];
        DB::table('grupo_integrantes')->insert($pivot);
        DB::table('grupo_integrantes')->insert(array_replace($pivot, ['grupo_id' => $idOtro]));
        expect(fn () => DB::table('grupo_integrantes')->insert($pivot))->toThrow(QueryException::class)
            ->and(fn () => DB::table('grupos_produccion')->where('id', $id)->delete())->toThrow(QueryException::class)
            ->and(fn () => DB::table('empleados')->where('id', $fixtures['empleados'][1]->id)->delete())->toThrow(QueryException::class)
            ->and(fn () => DB::table('empleados')->where('id', $fixtures['empleados'][0]->id)->delete())->toThrow(QueryException::class)
            ->and(fn () => DB::table('grupo_integrantes')->insert(['grupo_id' => $id, 'empleado_id' => 999999]))->toThrow(QueryException::class)
            ->and(fn () => DB::table('grupos_produccion')->insert(array_replace($grupo, ['categoria_id' => 999999])))->toThrow(QueryException::class)
            ->and(fn () => DB::table('grupos_produccion')->insert(array_replace($grupo, ['maestro_id' => 999999])))->toThrow(QueryException::class)
            ->and(fn () => $migration->down())->toThrow(RuntimeException::class, 'hay grupos');
        expect(Schema::hasTable('grupos_produccion'))->toBeTrue()->and(Schema::hasTable('grupo_integrantes'))->toBeTrue();
        // Eliminar exclusivamente configuración ficticia para ensayar down/up vacío.
        DB::table('grupo_integrantes')->delete();
        DB::table('grupos_produccion')->delete();
        $migration->down();
        expect(Schema::hasTable('grupos_produccion'))->toBeFalse()->and(Schema::hasTable('grupo_integrantes'))->toBeFalse();
        $migration->up();
        foreach ($tablas as $tabla) {
            expect(DB::table($tabla)->get()->toJson())->toBe($filas[$tabla]);
        }
    } finally {
        IsolatedMariaDb::restoreDatabase();
    }
});
