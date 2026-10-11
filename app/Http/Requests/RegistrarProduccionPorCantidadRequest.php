<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class RegistrarProduccionPorCantidadRequest extends FormRequest
{
    abstract protected function familia(): string;

    public function authorize(): bool
    {
        return true; // Acceso autenticado; permisos por rol fuera de este alcance.
    }

    protected function failedValidation(Validator $validator): void
    {
        // Familia del endpoint confiable, incluso con categoria manipulada o malformada.
        $this->session()->flash('produccion_familia', $this->familia());
        parent::failedValidation($validator);
    }

    public function rules(): array
    {
        return [
            'categoria' => ['required', 'string', Rule::in([$this->familia()])],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'turno_id' => ['required', 'integer', 'exists:turnos,id'],
            'detalles' => ['required', 'array', 'size:1', 'required_array_keys:0'],
            'detalles.*' => ['required', 'array'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            // Capacidad de las columnas INT, sin inventar un máximo operativo.
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'detalles.*.observacion' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > 65535) {
                    $fail('La observación no puede superar 65535 bytes.');
                }
            }],
            'detalles.*.participantes' => ['required', 'array', 'min:1'],
            'detalles.*.participantes.*' => ['required', 'array'],
            'detalles.*.participantes.*.empleado_id' => ['required', 'integer', 'exists:empleados,id'],
            'detalles.*.participantes.*.rol_produccion_id' => ['required', 'integer', 'exists:roles_produccion,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'array' => 'El campo :attribute debe ser una lista válida.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'string' => 'El campo :attribute debe ser texto.',
            'date_format' => 'La fecha debe ser una fecha válida con formato Y-m-d.',
            'categoria.in' => 'La categoría no corresponde a esta ruta de registro.',
            'detalles.size' => 'Registre exactamente un producto por envío.',
            'detalles.required_array_keys' => 'El producto debe enviarse en detalles[0].',
            'detalles.*.cantidad.min' => 'La cantidad debe ser un entero positivo.',
            'detalles.*.cantidad.max' => 'La cantidad excede la capacidad de almacenamiento.',
            'exists' => 'El valor seleccionado para :attribute no existe.',
            'detalles.*.foto.image' => 'La foto debe ser un archivo de imagen válido.',
            'detalles.*.foto.mimes' => 'La foto debe ser JPEG, PNG o WebP.',
            'detalles.*.foto.max' => 'La foto no puede superar 2 MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'categoria' => 'categoría', 'fecha' => 'fecha', 'turno_id' => 'turno',
            'detalles' => 'detalles', 'detalles.*' => 'detalle',
            'detalles.*.producto_id' => 'producto', 'detalles.*.cantidad' => 'cantidad',
            'detalles.*.forma' => 'forma', 'detalles.*.foto' => 'foto',
            'detalles.*.observacion' => 'observación', 'detalles.*.participantes' => 'participantes',
            'detalles.*.participantes.*' => 'participante',
            'detalles.*.participantes.*.empleado_id' => 'empleado',
            'detalles.*.participantes.*.rol_produccion_id' => 'rol de producción',
        ];
    }
}
