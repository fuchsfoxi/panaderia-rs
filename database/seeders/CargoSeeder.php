<?php

namespace Database\Seeders;

use App\Models\Cargo;
use Illuminate\Database\Seeder;

class CargoSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate sobre el modelo en vez de DB::table()->insert():
        // permite correr db:seed varias veces sin duplicar filas.
        foreach (['Panadero', 'Pastelero', 'Repostero', 'Atención al cliente', 'Administrador'] as $nombre) {
            Cargo::firstOrCreate(['nombre_cargos' => $nombre]);
        }
    }
}
