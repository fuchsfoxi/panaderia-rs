<?php

namespace App\Http\Requests;

use App\Models\Rol;
use App\Rules\PasswordUsuario;
use Illuminate\Validation\Rule;

class CrearUsuarioRequest extends GestionarUsuarioRequest
{
    public function rules(): array
    {
        $rolId = $this->input('rol_id');
        $esOperador = is_scalar($rolId) && filter_var($rolId, FILTER_VALIDATE_INT) !== false
            && Rol::whereKey($rolId)->where('nombre_roles', Rol::OPERADOR)->exists();

        return [
            'username' => ['bail', 'required', 'string', 'max:50', 'regex:/\A[a-zñ0-9_.-]+\z/u', Rule::unique('usuarios_sistema', 'username')],
            'rol_id' => ['bail', 'required', 'integer', Rule::exists('roles', 'id')->whereIn('nombre_roles', [Rol::ADMINISTRADOR, Rol::OPERADOR])],
            'turno_id' => [Rule::requiredIf($esOperador), 'nullable', 'integer', 'exists:turnos,id'],
            'password' => ['bail', 'required', 'string', new PasswordUsuario, 'confirmed'],
            'empleado_id' => ['prohibited'],
            'password_hash' => ['prohibited'],
            'activo' => ['prohibited'],
        ];
    }
}
