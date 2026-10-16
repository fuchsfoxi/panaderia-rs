<?php

namespace Database\Seeders;

use App\Models\Empleado;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        // No reasignar roles, empleados ni contraseñas de una cuenta existente.
        if (Usuario::where('username', 'admin')->exists()) {
            return;
        }

        $password = config('auth.initial_admin_password');

        // Bcrypt admite como máximo 72 bytes; exigir longitud mínima al aprovisionar.
        if (! is_string($password) || mb_strlen(trim($password)) < 16 || strlen($password) > 72) {
            throw new RuntimeException('Configura INITIAL_ADMIN_PASSWORD con al menos 16 caracteres y un máximo de 72 bytes para crear el administrador inicial.');
        }

        $rol = Rol::where('nombre_roles', Rol::ADMINISTRADOR)->sole();
        $empleado = Empleado::where('nombre_empleados', EmpleadoSeeder::NOMBRE_ADMINISTRADOR)
            ->where('numero_empleados', EmpleadoSeeder::NUMERO_ADMINISTRADOR)->sole();

        Usuario::create([
            'username' => 'admin',
            'password_hash' => Hash::make($password),
            'empleado_id' => $empleado->id,
            'rol_id' => $rol->id,
        ]);
    }
}
