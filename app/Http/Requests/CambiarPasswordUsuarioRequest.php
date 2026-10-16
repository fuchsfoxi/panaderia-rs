<?php

namespace App\Http\Requests;

use App\Rules\PasswordUsuario;

class CambiarPasswordUsuarioRequest extends GestionarUsuarioRequest
{
    public function rules(): array
    {
        return ['password' => ['bail', 'required', 'string', new PasswordUsuario, 'confirmed']];
    }
}
