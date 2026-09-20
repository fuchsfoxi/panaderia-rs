<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('usuarios_sistema')->insert([
            'username' => 'carlos.m',
            'password_hash' => Hash::make('password123'),
            'empleado_id' => 1, // Carlos M.
            'rol_id' => 1,      // Administrador
        ]);
    }
}