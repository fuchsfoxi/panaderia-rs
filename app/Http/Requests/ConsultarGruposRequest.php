<?php

namespace App\Http\Requests;

use App\Models\GrupoProduccion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;

class ConsultarGruposRequest extends GestionarGrupoRequest
{
    private bool $consultaValida = true;

    public function validationData(): array
    {
        return $this->query->all();
    }

    public function rules(): array
    {
        return ['categoria' => ['nullable', 'string', Rule::in(GrupoProduccion::CATEGORIAS)],
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647']];
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->consultaValida = false;
    }

    public function consultaValida(): bool
    {
        return $this->consultaValida;
    }

    public function categoria(): string
    {
        return is_string($this->query('categoria')) ? $this->query('categoria') : '';
    }

    public function erroresFiltros(): MessageBag
    {
        return $this->validator->errors();
    }

    public function messages(): array
    {
        return ['categoria.in' => 'Selecciona Pan, Torta o Bocadito.',
            'page.integer' => 'La página debe ser un entero positivo.',
            'page.min' => 'La página debe ser un entero positivo.',
            'page.max' => 'La página excede el límite permitido.'] + parent::messages();
    }
}
