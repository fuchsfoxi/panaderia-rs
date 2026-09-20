<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmpleadoSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('empleados')->insert([
            [
                'nombre_empleados' => 'Carlos M.',
                'numero_empleados' => '001',
                'cargo_id' => 5, // Administrador
            ],
            [
                'nombre_empleados' => 'Ana R.',
                'numero_empleados' => '002',
                'cargo_id' => 1, // Panadero
            ],
        ]);
    }
}