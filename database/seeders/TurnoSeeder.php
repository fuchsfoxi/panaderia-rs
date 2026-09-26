<?php

namespace Database\Seeders;

use App\Models\Turno;
use Illuminate\Database\Seeder;

class TurnoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Mañana', 'Noche'] as $nombre) {
            Turno::firstOrCreate(['nombre_turnos' => $nombre]);
        }
    }
}
