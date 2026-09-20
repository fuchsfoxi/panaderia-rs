<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insert([
            ['nombre_roles' => 'Administrador'],
            ['nombre_roles' => 'Encargado'],
            ['nombre_roles' => 'Operador'],
        ]);
    }
}