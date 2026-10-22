<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class GestionarGrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo && $this->user()->can('administrar-sistema');
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'Selecciona una referencia válida para :attribute.',
            'exists' => 'El valor seleccionado para :attribute no existe o no está permitido.',
            'nombre.max' => 'El nombre del grupo no debe superar los 100 caracteres.',
            'nombre.not_regex' => 'El nombre del grupo no admite caracteres de control.',
            'activo.boolean' => 'Selecciona un estado válido.',
            'integrantes.array' => 'Selecciona una lista válida de Ayudantes.',
            'integrantes.present' => 'Selecciona una lista de Ayudantes, que puede estar vacía para Torta.',
            'integrantes.list' => 'Los Ayudantes deben enviarse como una lista ordenada.',
            'integrantes.*.distinct' => 'No repitas empleados dentro del grupo.',
        ];
    }

    public function attributes(): array
    {
        return ['nombre' => 'nombre del grupo', 'categoria_id' => 'categoría', 'maestro_id' => 'Maestro responsable',
            'integrantes' => 'Ayudantes habituales', 'integrantes.*' => 'Ayudante', 'activo' => 'estado'];
    }
}
