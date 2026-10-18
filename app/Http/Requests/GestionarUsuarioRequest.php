<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

abstract class GestionarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-sistema') ?? false;
    }

    public function safeInput(): array
    {
        return collect($this->only('username', 'rol_id', 'turno_id', 'activo', 'empleado_id'))
            ->filter(fn ($value, $key) => ($key === 'empleado_id' && $value === null)
                || (is_scalar($value) && mb_strlen((string) $value) <= 50))->all();
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new ValidationException($validator, back()->withErrors($validator)->withInput($this->safeInput()));
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'exists' => 'El valor seleccionado para :attribute no es válido.',
            'username.regex' => 'Usa minúsculas de a-z o ñ, números, punto, guion o guion bajo, sin espacios.',
            'username.max' => 'El usuario no debe superar los 50 caracteres.',
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'activo.boolean' => 'El estado de la cuenta no es válido.',
            'prohibited' => 'El campo :attribute no está permitido.',
        ];
    }

    public function attributes(): array
    {
        return ['username' => 'usuario', 'rol_id' => 'rol', 'turno_id' => 'turno', 'password' => 'contraseña', 'activo' => 'estado', 'empleado_id' => 'empleado vinculado'];
    }
}
