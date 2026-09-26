<?php

namespace Database\Seeders;

use App\Models\RolProduccion;
use Illuminate\Database\Seeder;

class RolProduccionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Maestro', 'Ayudante'] as $nombre) {
            RolProduccion::firstOrCreate(['nombre_roles_produccion' => $nombre]);
        }
    }
}
