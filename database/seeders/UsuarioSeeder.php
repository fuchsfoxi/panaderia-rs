<?php

namespace Database\Seeders;

use App\Models\Empleado;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        // No reasignar roles, empleados ni contraseñas de una cuenta existente.
        if (Usuario::where('username', 'admin')->exists()) {
            return;
        }

        $rol = Rol::where('nombre_roles', Rol::ADMINISTRADOR)->sole();
        $empleado = Empleado::where('nombre_empleados', EmpleadoSeeder::NOMBRE_ADMINISTRADOR)
            ->where('numero_empleados', EmpleadoSeeder::NUMERO_ADMINISTRADOR)->sole();

        Usuario::create([
            'username' => 'admin',
            'password_hash' => Hash::make('1234'),
            'empleado_id' => $empleado->id,
            'rol_id' => $rol->id,
        ]);
    }
}
