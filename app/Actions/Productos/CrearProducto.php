<?php

namespace App\Actions\Productos;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class CrearProducto
{
    public function __construct(private ResolverUnidadProducto $unidades) {}

    public function ejecutar(array $datos): Producto
    {
        return DB::transaction(fn () => Producto::create($this->unidades->ejecutar($datos)));
    }
}
