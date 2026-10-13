<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;

class ConsultarProductosRequest extends FormRequest
{
    private bool $consultaValida = true;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->query('q'))) {
            $this->query->set('q', trim($this->query('q')));
        }
    }

    public function validationData(): array
    {
        return $this->query->all();
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'estado' => ['nullable', 'string', Rule::in(['todos', 'activos', 'inactivos'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->consultaValida = false;
    }

    public function consultaValida(): bool
    {
        return $this->consultaValida;
    }

    public function erroresFiltros(): MessageBag
    {
        return $this->validator->errors();
    }

    public function filtros(): array
    {
        $filtros = [];
        foreach (['q', 'categoria_id', 'estado'] as $campo) {
            $valor = $this->query($campo);
            $filtros[$campo] = is_scalar($valor) ? (string) $valor : '';
        }

        return $filtros;
    }

    public function pagina(): int
    {
        return $this->consultaValida ? (int) ($this->query('page') ?: 1) : 1;
    }

    public function messages(): array
    {
        return [
            'q.string' => 'La búsqueda debe ser texto.', 'q.max' => 'La búsqueda no puede superar 100 caracteres.',
            'categoria_id.integer' => 'La categoría seleccionada no es válida.', 'categoria_id.exists' => 'La categoría seleccionada no existe.',
            'estado.string' => 'El estado seleccionado no es válido.', 'estado.in' => 'Selecciona Todos, Activos o Inactivos.',
            'page.integer' => 'La página debe ser un entero positivo.', 'page.min' => 'La página debe ser un entero positivo.', 'page.max' => 'La página excede el límite permitido.',
        ];
    }
}
