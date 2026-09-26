<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate sobre el modelo en vez de DB::table()->insert():
        // permite correr db:seed varias veces sin duplicar filas.
        foreach (['Administrador', 'Encargado', 'Operador'] as $nombre) {
            Rol::firstOrCreate(['nombre_roles' => $nombre]);
        }
    }
}
