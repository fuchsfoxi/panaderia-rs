<?php

namespace App\Http\Requests;

class CambiarEstadoUsuarioRequest extends GestionarUsuarioRequest
{
    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
