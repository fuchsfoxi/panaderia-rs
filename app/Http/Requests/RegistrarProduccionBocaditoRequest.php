<?php

namespace App\Http\Requests;

class RegistrarProduccionBocaditoRequest extends RegistrarProduccionPorCantidadRequest
{
    protected function familia(): string
    {
        return 'bocadito';
    }
}
