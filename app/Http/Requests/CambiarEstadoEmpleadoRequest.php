<?php

namespace App\Http\Requests;

class CambiarEstadoEmpleadoRequest extends GestionarEmpleadoRequest
{
    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
