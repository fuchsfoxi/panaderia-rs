<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarProduccionRequest extends FormRequest
{
    private array $contexto = [];

    private bool $consultaValida = true;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Al recuperar un POST, sus campos prevalecen sobre los filtros del GET.
        $this->contexto = [
            'fecha' => $this->old('fecha', $this->query('fecha', now()->toDateString())),
            'turno_id' => $this->old('turno_id', $this->query('turno_id')),
        ];
    }

    public function validationData(): array
    {
        return $this->contexto;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'turno_id' => ['nullable', 'integer', 'exists:turnos,id'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        // Un GET inválido conserva la página y su aviso, sin redirección a sí mismo.
        // El controlador solo puede consultar resultados si consultaValida() es true.
        $this->consultaValida = false;
    }

    public function consultaValida(): bool
    {
        return $this->consultaValida;
    }

    public function filtros(): array
    {
        // Se validan los valores originales antes de sanearlos para los atributos HTML.
        return [
            'fecha' => is_scalar($this->contexto['fecha']) ? (string) $this->contexto['fecha'] : '',
            'turno_id' => is_scalar($this->contexto['turno_id']) ? (string) $this->contexto['turno_id'] : null,
        ];
    }

    public function errorConsulta(): ?string
    {
        return ! $this->consultaValida && ! $this->session()->hasOldInput()
            ? 'La fecha o el turno de consulta no son válidos.' : null;
    }
}
