<?php

namespace App\Http\Requests;

class CambiarEstadoGrupoRequest extends GestionarGrupoRequest
{
    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
