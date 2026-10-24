<?php

namespace App\Actions\Productos;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class CrearProducto
{
    public function __construct(private ResolverUnidadProducto $unidades) {}

    public function ejecutar(array $datos): Producto
    {
        return DB::transaction(function () use ($datos): Producto {
            // Coordina altas manuales e importaciones sin añadir unicidad a datos legacy.
            Categoria::whereKey($datos['categoria_id'])->lockForUpdate()->firstOrFail();

            return Producto::create($this->unidades->ejecutar($datos));
        }, 3);
    }
}
