<?php

namespace App\Actions\Produccion;

use App\Models\Produccion;

class RegistrarProduccionBocadito
{
    public function __construct(private RegistrarProduccionPorCantidad $registrar) {}

    public function ejecutar(array $datos, int $usuarioId): Produccion
    {
        return $this->registrar->ejecutar($datos, $usuarioId, 'Bocadito', fn (array $detalle) => []);
    }
}
