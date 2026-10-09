<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\MessageBag;

class ConsultarHistorialProduccionRequest extends FormRequest
{
    private bool $consultaValida = true;

    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        return $this->query->all();
    }

    public function rules(): array
    {
        $fechaFin = ['nullable', 'date_format:Y-m-d'];
        if ($this->query('fecha_inicio') !== null && $this->query('fecha_inicio') !== '') {
            $fechaFin[] = 'after_or_equal:fecha_inicio';
        }

        return [
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin' => $fechaFin,
            'turno_id' => ['nullable', 'integer', 'exists:turnos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_inicio.date_format' => 'La fecha desde debe ser válida y tener formato Y-m-d.',
            'fecha_fin.date_format' => 'La fecha hasta debe ser válida y tener formato Y-m-d.',
            'fecha_fin.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
            'turno_id.integer' => 'El turno seleccionado no es válido.',
            'turno_id.exists' => 'El turno seleccionado no existe.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        // Igual que Producción: mostrar el GET inválido sin redirigir a sí mismo.
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
        foreach (['fecha_inicio', 'fecha_fin', 'turno_id'] as $campo) {
            $valor = $this->query($campo);
            $filtros[$campo] = is_scalar($valor) ? (string) $valor : '';
        }

        return $filtros;
    }
}
