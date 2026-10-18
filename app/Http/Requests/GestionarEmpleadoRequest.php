<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class GestionarEmpleadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-sistema') ?? false;
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'Selecciona un rol actual válido.',
            'exists' => 'Selecciona Maestro, Ayudante o Practicante.',
            'nombre_empleados.max' => 'El nombre no debe superar los 100 caracteres.',
            'nombre_empleados.regex' => 'El nombre debe contener letras y solo admite espacios, puntos, apóstrofos y guiones.',
            'activo.boolean' => 'Selecciona un estado válido.',
        ];
    }

    public function attributes(): array
    {
        return ['nombre_empleados' => 'nombre completo', 'rol_produccion_id' => 'rol actual', 'activo' => 'estado'];
    }
}
