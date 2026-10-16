<?php

namespace App\Actions\Productos;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Validation\ValidationException;

class ResolverUnidadProducto
{
    public function ejecutar(array $datos, ?Producto $producto = null): array
    {
        $familia = Categoria::find($datos['categoria_id'] ?? $producto?->categoria_id)?->nombre_categorias;
        $codigo = UnidadMedida::codigoParaFamilia($familia ?? '');
        $unidad = $codigo ? UnidadMedida::where('codigo', $codigo)->value('id') : null;
        if ($unidad === null) {
            throw ValidationException::withMessages(['unidad_medida_id' => 'Falta la unidad canónica de esta categoría. Ejecuta UnidadMedidaSeeder y resuelve cualquier ambigüedad del catálogo.']);
        }
        $esperada = (int) $unidad;
        // Lecturas actuales: una producción concurrente puede haberse confirmado
        // después del snapshot abierto al consultar la categoría.
        if ($producto) {
            foreach (['detallesPan', 'detallesTorta', 'detallesBocadito'] as $relacion) {
                if ($producto->$relacion()->lockForUpdate()->first(['id'])) {
                    if ((int) ($datos['categoria_id'] ?? $producto->categoria_id) !== $producto->categoria_id) {
                        throw ValidationException::withMessages(['categoria_id' => 'Este producto ya tiene producción registrada. Su categoría no se puede cambiar.']);
                    }
                    // Compatibilidad explícita: no corregir asociaciones históricas.
                    $esperada = $producto->unidad_medida_id;
                    break;
                }
            }
        }
        if (array_key_exists('unidad_medida_id', $datos) && (int) $datos['unidad_medida_id'] !== $esperada) {
            throw ValidationException::withMessages(['unidad_medida_id' => 'La unidad no corresponde a la categoría o está protegida por el historial.']);
        }
        $datos['unidad_medida_id'] = $esperada;

        return $datos;
    }
}
