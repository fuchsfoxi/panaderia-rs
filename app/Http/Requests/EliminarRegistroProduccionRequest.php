<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EliminarRegistroProduccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administrar-sistema') ?? false;
    }

    public function rules(): array
    {
        return ['confirmado' => ['required', 'accepted']];
    }

    public function messages(): array
    {
        return [
            'confirmado.required' => 'Debes confirmar la eliminación del registro.',
            'confirmado.accepted' => 'Debes confirmar la eliminación del registro.',
        ];
    }

    public function filtrosRetorno(): array
    {
        $filtros = array_intersect_key($this->query(), array_flip(['fecha_inicio', 'fecha_fin', 'turno_id', 'categoria', 'page']));

        return array_filter($filtros, fn ($valor) => is_scalar($valor) && (string) $valor !== '');
    }

    protected function getRedirectUrl(): string
    {
        return route('history.index', $this->filtrosRetorno());
    }
}
