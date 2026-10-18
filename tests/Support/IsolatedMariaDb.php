<?php

namespace Tests\Support;

use App\Models\Cargo;
use App\Models\Categoria;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Empleado;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\RolProduccion;
use App\Models\Turno;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class IsolatedMariaDb
{
    public static function connect(string $database = 'sprint4_models_test'): void
    {
        if ($database === 'sprint4_models_test' && getenv('SPRINT4_CASE_DATABASE')) {
            $database = getenv('SPRINT4_CASE_DATABASE');
        }
        $socket = getenv('SPRINT4_TEST_SOCKET');
        $directory = $socket ? realpath(dirname($socket)) : false;

        if (! $directory || ! str_starts_with($directory, '/tmp/sprint4-')
            || (! in_array($database, ['sprint4_models_test', 'sprint4_migration_test'], true)
                && ! preg_match('/\Asprint4_case_[a-f0-9]{12}\z/', $database))) {
            throw new RuntimeException('Estas pruebas requieren una instancia temporal propia en /tmp/sprint4-*.');
        }

        config([
            'database.default' => 'sprint4_test',
            'database.connections.sprint4_test' => [
                'driver' => 'mariadb', 'url' => null,
                'unix_socket' => $socket, 'host' => 'localhost', 'port' => 0,
                'database' => $database, 'username' => 'root', 'password' => '',
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '', 'strict' => true,
            ],
        ]);
        DB::purge('sprint4_test');

        $server = DB::selectOne('SELECT @@datadir AS datadir, DATABASE() AS db');
        if (realpath($server->datadir) !== $directory.'/mariadb-data' || $server->db !== $database) {
            throw new RuntimeException('La conexión no pertenece al servidor desechable esperado. No se ejecutarán migraciones.');
        }
    }

    public static function migrateInNewDatabase(array $paths = []): int
    {
        // Verificar servidor/datadir antes de crear una base vacía, sin borrar otra.
        self::connect();
        $database = 'sprint4_case_'.bin2hex(random_bytes(6));
        DB::statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        // Los workers de concurrencia heredan exclusivamente esta identidad temporal.
        putenv('SPRINT4_CASE_DATABASE='.$database);
        self::connect();

        return Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]
            + ($paths ? ['--path' => $paths, '--realpath' => true] : []));
    }

    public static function restoreDatabase(): void
    {
        putenv('SPRINT4_CASE_DATABASE');
        self::connect();
    }

    public static function fixtures(string $nombreRol = Rol::OPERADOR, bool $canonicas = true): array
    {
        $tieneFactor = Schema::hasColumn('productos', 'panes_por_lata');
        $canonicas = $canonicas && $tieneFactor && Schema::hasColumn('unidades_medida', 'codigo');
        $cargo = Cargo::create(['nombre_cargos' => 'Cargo ficticio']);
        $empleados = collect(range(1, 3))->map(fn ($n) => Empleado::create([
            'nombre_empleados' => 'Empleado ficticio '.$n, 'cargo_id' => $cargo->id,
        ]));
        $rol = Rol::create(['nombre_roles' => $nombreRol]);
        $turno = Turno::create(['nombre_turnos' => 'Mañana']);
        $usuario = Usuario::create([
            'username' => 'sprint4.ficticio', 'password_hash' => Hash::make('clave-ficticia-sprint4'),
            'empleado_id' => $empleados->first()->id, 'rol_id' => $rol->id,
        ] + (Schema::hasColumn('usuarios_sistema', 'turno_id') ? ['turno_id' => $nombreRol === Rol::OPERADOR ? $turno->id : null] : []))->refresh();
        $unidad = UnidadMedida::create(['nombre_unidades_medida' => $canonicas ? 'Lata ficticia' : 'Unidad ficticia', 'equivalencia_unidades' => '1.00']
            + ($canonicas ? ['codigo' => UnidadMedida::LATA] : []));
        $unidadDirecta = $canonicas ? UnidadMedida::create(['codigo' => UnidadMedida::UNIDAD, 'nombre_unidades_medida' => 'Unidad ficticia', 'equivalencia_unidades' => 1]) : $unidad;
        $roles = collect(['Maestro', 'Ayudante', 'Practicante'])->mapWithKeys(fn ($nombre) => [
            $nombre => RolProduccion::create(['nombre_roles_produccion' => $nombre]),
        ]);
        if (Schema::hasColumn('empleados', 'rol_produccion_id')) {
            foreach ($empleados as $indice => $empleado) {
                $empleado->update(['rol_produccion_id' => $roles[$indice === 0 ? 'Maestro' : 'Ayudante']->id, 'activo' => true]);
            }
        }
        $productos = collect(['Pan', 'Torta', 'Bocadito'])->mapWithKeys(function ($familia) use ($unidad, $unidadDirecta, $canonicas, $tieneFactor) {
            $categoria = Categoria::create(['nombre_categorias' => $familia]);

            return [$familia => Producto::create([
                'nombre_p' => $familia.' ficticio', 'categoria_id' => $categoria->id,
                'unidad_medida_id' => ($familia === 'Pan' ? $unidad : $unidadDirecta)->id,
            ] + ($tieneFactor ? ['panes_por_lata' => $canonicas && $familia === 'Pan' ? 12 : null] : []))];
        });

        return compact('cargo', 'empleados', 'rol', 'usuario', 'unidad', 'turno', 'roles', 'productos');
    }

    public static function detail(string $class, Produccion $produccion, array $fixtures, array $extra = []): Model
    {
        $familia = match ($class) {
            DetallePan::class => 'Pan', DetalleTorta::class => 'Torta', default => 'Bocadito',
        };
        $attributes = ['produccion_id' => $produccion->id, 'producto_id' => $fixtures['productos'][$familia]->id];
        $attributes += $class === DetalleTorta::class
            ? ['forma' => 'circular', 'foto' => 'pruebas/torta-ficticia.jpg']
            : ['cantidad' => 3];
        if ($class === DetalleTorta::class && Schema::hasColumn('detalle_torta', 'cantidad')) {
            $attributes['cantidad'] = 1;
        }
        if ($class === DetallePan::class) {
            $attributes += ['unidad_medida_id' => $fixtures['productos']['Pan']->unidad_medida_id, 'turno_id' => $fixtures['turno']->id];
        }

        return $class::create(array_replace($attributes, $extra));
    }
}
