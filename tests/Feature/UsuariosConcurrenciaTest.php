<?php

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\IsolatedMariaDb;

test('dos administradores desactivándose simultáneamente conservan uno activo', function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    IsolatedMariaDb::connect();
    expect(IsolatedMariaDb::migrateInNewDatabase())->toBe(0);
    $rol = Rol::create(['nombre_roles' => Rol::ADMINISTRADOR]);
    $usuarios = collect(['admin_ficticio_a', 'admin_ficticio_b'])->map(fn ($username) => Usuario::create([
        'username' => $username, 'rol_id' => $rol->id, 'password_hash' => Hash::make(Str::random(32)), 'activo' => true,
    ]));
    $procesos = [];
    try {
        DB::beginTransaction();
        DB::table('roles')->where('id', $rol->id)->lockForUpdate()->first();
        foreach ($usuarios as $usuario) {
            $proceso = proc_open([PHP_BINARY, base_path('tests/Support/usuario-estado-concurrente.php')], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $pipes, base_path());
            $procesos[] = [$proceso, $pipes];
            fwrite($pipes[0], (string) $usuario->id);
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
        expect($resultados)->toEqualCanonicalizing(['DESACTIVADO', 'PROTEGIDO'])
            ->and(Usuario::where('activo', true)->count())->toBe(1);
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
        IsolatedMariaDb::restoreDatabase();
    }
});
