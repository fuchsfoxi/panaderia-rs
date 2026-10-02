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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class IsolatedMariaDb
{
    public static function connect(string $database = 'sprint4_models_test'): void
    {
        $socket = getenv('SPRINT4_TEST_SOCKET');
        $directory = $socket ? realpath(dirname($socket)) : false;

        if (! $directory || ! str_starts_with($directory, '/tmp/sprint4-')
            || ! in_array($database, ['sprint4_models_test', 'sprint4_migration_test'], true)) {
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

    public static function fixtures(): array
    {
        $cargo = Cargo::create(['nombre_cargos' => 'Cargo ficticio']);
        $empleados = collect(range(1, 3))->map(fn ($n) => Empleado::create([
            'nombre_empleados' => 'Empleado ficticio '.$n, 'cargo_id' => $cargo->id,
        ]));
        $rol = Rol::create(['nombre_roles' => 'Rol ficticio']);
        $usuario = Usuario::create([
            'username' => 'sprint4.ficticio', 'password_hash' => Hash::make('clave-ficticia-sprint4'),
            'empleado_id' => $empleados->first()->id, 'rol_id' => $rol->id,
        ]);
        $unidad = UnidadMedida::create(['nombre_unidades_medida' => 'Unidad ficticia', 'equivalencia_unidades' => '1.00']);
        $turno = Turno::create(['nombre_turnos' => 'Mañana']);
        $roles = collect(['Maestro', 'Ayudante', 'Practicante'])->mapWithKeys(fn ($nombre) => [
            $nombre => RolProduccion::create(['nombre_roles_produccion' => $nombre]),
        ]);
        $productos = collect(['Pan', 'Torta', 'Bocadito'])->mapWithKeys(function ($familia) use ($unidad) {
            $categoria = Categoria::create(['nombre_categorias' => $familia]);

            return [$familia => Producto::create([
                'nombre_p' => $familia.' ficticio', 'categoria_id' => $categoria->id,
                'unidad_medida_id' => $unidad->id,
            ])];
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
        if ($class === DetallePan::class) {
            $attributes += ['unidad_medida_id' => $fixtures['unidad']->id, 'turno_id' => $fixtures['turno']->id];
        }

        return $class::create(array_replace($attributes, $extra));
    }
}
