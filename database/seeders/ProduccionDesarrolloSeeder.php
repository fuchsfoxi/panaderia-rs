<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Datos provisionales para desarrollar el formulario de Producción.
 * Lata es una UNIDAD PROVISIONAL DE DESARROLLO hasta recibir el catálogo real.
 * No registrar este seeder en DatabaseSeeder ni usarlo en producción.
 */
class ProduccionDesarrolloSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('ProduccionDesarrolloSeeder solo puede ejecutarse en local o testing.');
        }

        DB::transaction(function (): void {
            // Requiere CategoriaSeeder aplicado; no inventar una categoría o su ID.
            $categoria = Categoria::where('nombre_categorias', 'Pan')->firstOrFail();

            // UNIDAD PROVISIONAL DE DESARROLLO; no acredita la unidad definitiva.
            $unidad = UnidadMedida::updateOrCreate(
                ['nombre_unidades_medida' => 'Lata'],
                ['equivalencia_unidades' => '1.00'],
            );

            // Repetir este seeder restablece estos valores del producto de desarrollo.
            // Pan Yema = 12 panes/lata es un factor confirmado, no un ID de catálogo.
            Producto::updateOrCreate(
                ['nombre_p' => 'Pan Yema'],
                [
                    'categoria_id' => $categoria->id,
                    'unidad_medida_id' => $unidad->id,
                    'activo' => true,
                    'temporada_fe' => null,
                    'panes_por_lata' => 12,
                ],
            );
        });
    }
}
