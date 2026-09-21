<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CargoSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('cargos')->insert([
            ['nombre_cargos' => 'Panadero'],
            ['nombre_cargos' => 'Pastelero'],
            ['nombre_cargos' => 'Repostero'],
            ['nombre_cargos' => 'Atención al cliente'],
            ['nombre_cargos' => 'Administrador'],
        ]);
    }
}