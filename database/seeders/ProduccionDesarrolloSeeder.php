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
 * Utiliza el catálogo canónico; los productos de ejemplo siguen siendo provisionales.
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

            $this->call(UnidadMedidaSeeder::class);
            $unidad = UnidadMedida::where('codigo', UnidadMedida::LATA)->sole();

            // Repetir este seeder restablece estos valores del producto de desarrollo.
            // Pan Yema = 12 panes/lata es un factor confirmado, no un ID de catálogo.
            $existente = Producto::where('nombre_p', 'Pan Yema')->first();
            if ($existente && ($existente->detallesPan()->exists() || $existente->detallesTorta()->exists() || $existente->detallesBocadito()->exists())
                && ($existente->categoria_id !== $categoria->id || $existente->unidad_medida_id !== $unidad->id)) {
                throw new RuntimeException('Pan Yema tiene historial con otra asociación; no se reasignará.');
            }
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
