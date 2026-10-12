<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([Rol::ADMINISTRADOR, Rol::OPERADOR] as $nombre) {
            Rol::firstOrCreate(['nombre_roles' => $nombre]);
        }
    }
}
