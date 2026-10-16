<?php

namespace App\Actions\Productos;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActualizarProducto
{
    public function __construct(private ResolverUnidadProducto $unidades) {}

    public function ejecutar(int $productoId, array $datos): Producto
    {
        return DB::transaction(function () use ($productoId, $datos): Producto {
            // Respetar categoría → producto, igual que los tres escritores de Producción.
            $categoriaActual = Producto::whereKey($productoId)->value('categoria_id');
            $categoriaDestino = $datos['categoria_id'] ?? $categoriaActual;
            $categoriasBloqueadas = Categoria::whereIn('id', array_unique([$categoriaActual, $categoriaDestino]))
                ->orderBy('id')->sharedLock()->pluck('id')->all();
            $producto = Producto::whereKey($productoId)->lockForUpdate()->firstOrFail();
            if (! in_array($producto->categoria_id, $categoriasBloqueadas, true)) {
                throw ValidationException::withMessages(['producto' => 'La categoría del producto cambió mientras editabas. Vuelve a abrir el formulario antes de guardar.']);
            }
            if (! in_array((int) $categoriaDestino, $categoriasBloqueadas, true)) {
                throw ValidationException::withMessages(['categoria_id' => 'La categoría seleccionada ya no está disponible.']);
            }
            if (array_key_exists('categoria_id', $datos) || array_key_exists('unidad_medida_id', $datos)) {
                $datos = $this->unidades->ejecutar($datos, $producto);
            }
            $cambiaCategoria = isset($datos['categoria_id']) && $producto->categoria_id !== (int) $datos['categoria_id'];
            $cambiaUnidad = isset($datos['unidad_medida_id']) && $producto->unidad_medida_id !== (int) $datos['unidad_medida_id'];

            if ($cambiaCategoria || $cambiaUnidad) {
                foreach (['detallesPan', 'detallesTorta', 'detallesBocadito'] as $relacion) {
                    // Lectura actual incluso si otra consulta abrió un snapshot de la transacción.
                    if ($producto->$relacion()->lockForUpdate()->first(['id'])) {
                        $errores = [];
                        if ($cambiaCategoria) {
                            $errores['categoria_id'] = 'Este producto ya tiene producción registrada. Su categoría no se puede cambiar.';
                        }
                        if ($cambiaUnidad) {
                            $errores['unidad_medida_id'] = 'Este producto ya tiene producción registrada. Su unidad de medida no se puede cambiar.';
                        }
                        throw ValidationException::withMessages($errores);
                    }
                }
            }

            $producto->update(array_intersect_key($datos, array_flip(['nombre_p', 'categoria_id', 'unidad_medida_id', 'panes_por_lata', 'activo'])));

            return $producto;
        }, 3);
    }
}
