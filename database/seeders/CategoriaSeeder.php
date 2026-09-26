<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Pan', 'Torta', 'Bocadito'] as $nombre) {
            Categoria::firstOrCreate(['nombre_categorias' => $nombre]);
        }
    }
}
