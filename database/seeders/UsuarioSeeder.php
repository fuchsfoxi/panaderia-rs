<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Empleado;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        // primero creo lo que el usuario necesita por las llaves foráneas
        // firstOrCreate busca y solo crea si no existe, así puedo correr el seeder varias veces
        $rol = Rol::firstOrCreate(['nombre_roles' => 'Administrador']);

        $cargo = Cargo::firstOrCreate(['nombre_cargos' => 'Administrador']);

        $empleado = Empleado::firstOrCreate(
            ['nombre_empleados' => 'Administrador del sistema'],
            ['cargo_id' => $cargo->id]
        );

        // por último el usuario, con la contraseña hasheada desde PHP
        // busca por username; si no existe, lo crea con los demás datos
        Usuario::firstOrCreate(
            ['username' => 'admin'],
            [
                'password_hash' => Hash::make('1234'),
                'empleado_id' => $empleado->id,
                'rol_id' => $rol->id,
            ]
        );
    }
}