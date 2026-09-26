<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class ProductoSeeder extends Seeder
{
    public function run(): void
    {
        $categoria = fn (string $nombre) => Categoria::where('nombre_categorias', $nombre)->value('id');

        $unidad = fn (string $nombre) => UnidadMedida::where('nombre_unidades_medida', $nombre)->value('id');

        // Todos los productos se siembran en 'unidad' (la base, equivalencia 1.00
        // confirmada). Los productos de pan NO se siembran en 'lata' ni 'coche'
        // porque esas equivalencias estan en NULL y darian conteos erroneos.
        $productos = [
            ['nombre_p' => 'Pan Francés', 'categoria' => 'Pan', 'unidad' => 'unidad'],
            ['nombre_p' => 'Pan Yema', 'categoria' => 'Pan', 'unidad' => 'unidad'],
            ['nombre_p' => 'Pan Integral', 'categoria' => 'Pan', 'unidad' => 'unidad'],
            ['nombre_p' => 'Pan Carioca', 'categoria' => 'Pan', 'unidad' => 'unidad'],
            ['nombre_p' => 'Torta de Chocolate', 'categoria' => 'Torta', 'unidad' => 'unidad'],
            ['nombre_p' => 'Torta de Vainilla', 'categoria' => 'Torta', 'unidad' => 'unidad'],
            ['nombre_p' => 'Alfajorcitos', 'categoria' => 'Bocadito', 'unidad' => 'unidad'],
            ['nombre_p' => 'Conitos', 'categoria' => 'Bocadito', 'unidad' => 'unidad'],
            ['nombre_p' => 'Empanaditas de Pollo', 'categoria' => 'Bocadito', 'unidad' => 'unidad'],
            ['nombre_p' => 'Pionono', 'categoria' => 'Bocadito', 'unidad' => 'unidad'],
        ];

        foreach ($productos as $producto) {
            Producto::firstOrCreate(
                ['nombre_p' => $producto['nombre_p']],
                [
                    'categoria_id' => $categoria($producto['categoria']),
                    'unidad_medida_id' => $unidad($producto['unidad']),
                    'activo' => true,
                ]
            );
        }
    }
}
