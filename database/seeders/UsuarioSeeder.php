<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        // el empleado 1 (Carlos M.) y el rol 1 (Administrador) los crean EmpleadoSeeder y RolSeeder
        // por eso este seeder tiene que correr después de esos
        // la contraseña se hashea desde PHP, nunca pegada a mano en la base de datos
        Usuario::create([
            'username' => 'admin',
            'password_hash' => Hash::make('1234'),
            'empleado_id' => 1,
            'rol_id' => 1,
        ]);
    }
}