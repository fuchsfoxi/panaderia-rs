<?php

use App\Models\Empleado;
use App\Models\Rol;
use App\Models\Turno;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    IsolatedMariaDb::connect();
    expect(IsolatedMariaDb::migrateInNewDatabase())->toBe(0);
    $this->admin = Rol::create(['nombre_roles' => Rol::ADMINISTRADOR]);
    $this->operador = Rol::create(['nombre_roles' => Rol::OPERADOR]);
    $this->turno = Turno::create(['nombre_turnos' => 'Mañana']);
    $this->usuarios = collect(['administrador_a', 'administrador_b'])->map(fn ($nombre) => Usuario::create([
        'username' => $nombre, 'rol_id' => $this->admin->id, 'activo' => true, 'password_hash' => Hash::make('clave-ficticia'),
    ]));
});

afterEach(function () {
    if (getenv('SPRINT4_CASE_DATABASE')) {
        IsolatedMariaDb::restoreDatabase();
    }
});

function ejecutarEdicionesConcurrentes(array $peticiones, int $rolId): array
{
    $procesos = [];
    try {
        DB::beginTransaction();
        DB::table('roles')->where('id', $rolId)->lockForUpdate()->first();
        foreach ($peticiones as $peticion) {
            $proceso = proc_open([PHP_BINARY, base_path('tests/Support/usuario-edicion-concurrente.php')], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $pipes, base_path());
            $procesos[] = [$proceso, $pipes];
            fwrite($pipes[0], json_encode($peticion, JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            expect(trim(fgets($pipes[1]) ?: ''))->toBe('LISTO');
        }
        DB::commit();
        $resultados = [];
        foreach ($procesos as [$proceso, $pipes]) {
            $resultados[] = trim(stream_get_contents($pipes[1]));
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            expect(proc_close($proceso))->toBe(0, $error);
        }
        $procesos = [];

        return $resultados;
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($procesos as [$proceso, $pipes]) {
            if (is_resource($proceso)) {
                proc_terminate($proceso);
                proc_close($proceso);
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
        }
    }
}

test('dos degradaciones simultáneas conservan al último Administrador activo', function (bool $mezclarEstado) {
    $peticiones = $this->usuarios->map(fn ($usuario, $i) => [
        'usuario' => $usuario->id, 'actor' => $usuario->id, 'operacion' => $mezclarEstado && $i === 1 ? 'estado' : 'editar',
        'datos' => ['username' => $usuario->username, 'rol_id' => $this->operador->id, 'turno_id' => $this->turno->id],
    ])->all();
    $resultados = ejecutarEdicionesConcurrentes($peticiones, $this->admin->id);
    expect(collect($resultados)->filter(fn ($resultado) => $resultado === 'ACTUALIZADO'))->toHaveCount(1)
        ->and(collect($resultados)->filter(fn ($resultado) => str_starts_with($resultado, 'PROTEGIDO:')))->toHaveCount(1)
        ->and(Usuario::where('rol_id', $this->admin->id)->where('activo', true)->count())->toBe(1);
})->with([false, true]);

test('dos cuentas intentando vincular simultáneamente al mismo empleado solo asignan una', function () {
    $empleado = Empleado::create(['nombre_empleados' => 'Trabajador ficticio', 'activo' => true]);
    $actor = $this->usuarios[0];
    $otro = Usuario::create(['username' => 'operador_ficticio', 'rol_id' => $this->operador->id, 'turno_id' => $this->turno->id,
        'activo' => true, 'password_hash' => Hash::make('clave-ficticia')]);
    $peticiones = collect([$this->usuarios[1], $otro])->map(fn ($usuario) => [
        'usuario' => $usuario->id, 'actor' => $actor->id, 'operacion' => 'editar',
        'datos' => ['username' => $usuario->username, 'rol_id' => $usuario->rol_id, 'turno_id' => $this->turno->id, 'empleado_id' => $empleado->id],
    ])->all();
    expect(ejecutarEdicionesConcurrentes($peticiones, $this->admin->id))->toEqualCanonicalizing(['ACTUALIZADO', 'PROTEGIDO:empleado_id'])
        ->and(Usuario::where('empleado_id', $empleado->id)->count())->toBe(1)
        ->and(Empleado::count())->toBe(1);
});
