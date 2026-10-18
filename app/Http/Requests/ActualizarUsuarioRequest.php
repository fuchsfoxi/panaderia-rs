<?php

namespace App\Http\Requests;

use App\Models\Rol;
use Illuminate\Validation\Rule;

class ActualizarUsuarioRequest extends GestionarUsuarioRequest
{
    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $rolId = $this->input('rol_id');
        $esOperador = is_scalar($rolId) && filter_var($rolId, FILTER_VALIDATE_INT) !== false
            && Rol::whereKey($rolId)->where('nombre_roles', Rol::OPERADOR)->exists();
        $username = ['bail', 'required', 'string', 'max:50'];
        // Permitir conservar una identidad legacy exacta al editar otros datos.
        if ($this->input('username') !== $usuario->username) {
            $username[] = 'regex:/\A[a-zñ0-9_.-]+\z/u';
        }
        $username[] = Rule::unique('usuarios_sistema', 'username')->ignore($usuario->id);

        return [
            'username' => $username,
            'rol_id' => ['bail', 'required', 'integer', Rule::exists('roles', 'id')->whereIn('nombre_roles', [Rol::ADMINISTRADOR, Rol::OPERADOR])],
            'turno_id' => [Rule::requiredIf($esOperador), 'nullable', 'integer', 'exists:turnos,id'],
            'empleado_id' => ['bail', 'nullable', 'integer', 'exists:empleados,id', Rule::unique('usuarios_sistema', 'empleado_id')->ignore($usuario->id)],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
            'password_hash' => ['prohibited'],
            'activo' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + ['empleado_id.unique' => 'Este empleado ya está vinculado a otra cuenta.'];
    }
}
