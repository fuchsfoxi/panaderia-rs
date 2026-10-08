<?php

namespace App\Http\Requests;

use App\Models\DetallePan;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarProduccionPanRequest extends FormRequest
{
    // Capacidad en bytes de la columna TEXT; no es un límite operativo inventado.
    private const MAX_OBSERVACION_BYTES = 65535;

    public function authorize(): bool
    {
        // Las rutas conservan auth; la matriz de permisos por rol sigue pendiente.
        return true;
    }

    public function rules(): array
    {
        return [
            'categoria' => ['required', 'string', Rule::in(['pan'])],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'turno_id' => ['required', 'integer', 'exists:turnos,id'],
            'detalles' => ['required', 'array', 'size:1', 'required_array_keys:0'],
            'detalles.*' => ['required', 'array'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.coches' => ['required', 'integer', 'min:0', 'max:'.intdiv(DetallePan::MAX_CANTIDAD_LATAS, DetallePan::LATAS_POR_COCHE)],
            'detalles.*.latas_adicionales' => ['required', 'integer', 'between:0,'.(DetallePan::LATAS_POR_COCHE - 1)],
            'detalles.*.observacion' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > self::MAX_OBSERVACION_BYTES) {
                    $fail('La observación no puede superar '.self::MAX_OBSERVACION_BYTES.' bytes.');
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
            'min.numeric' => 'El campo :attribute no puede ser negativo.',
            'min.array' => 'Debe indicar al menos un elemento en :attribute.',
            'max.numeric' => 'El campo :attribute excede la cantidad máxima permitida.',
            'detalles.size' => 'Registre exactamente un producto de Pan por envío.',
            'detalles.required_array_keys' => 'El producto de Pan debe enviarse en detalles[0].',
            'detalles.*.latas_adicionales.between' => 'Las latas deben estar entre 0 y '.(DetallePan::LATAS_POR_COCHE - 1).'.',
            'categoria.in' => 'Solo se puede registrar Pan. Torta y Bocadito todavía no están implementados.',
            'turno_id.exists' => 'El turno seleccionado no existe.',
            'detalles.*.producto_id.exists' => 'El producto seleccionado no existe.',
            'detalles.*.participantes.*.empleado_id.exists' => 'El empleado seleccionado no existe.',
            'detalles.*.participantes.*.rol_produccion_id.exists' => 'El rol de producción seleccionado no existe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'categoria' => 'categoría',
            'fecha' => 'fecha',
            'turno_id' => 'turno',
            'detalles' => 'detalles de Pan',
            'detalles.*' => 'detalle de Pan',
            'detalles.*.producto_id' => 'producto',
            'detalles.*.coches' => 'coches',
            'detalles.*.latas_adicionales' => 'latas',
            'detalles.*.observacion' => 'observación',
            'detalles.*.participantes' => 'participantes',
            'detalles.*.participantes.*' => 'participante',
            'detalles.*.participantes.*.empleado_id' => 'empleado',
            'detalles.*.participantes.*.rol_produccion_id' => 'rol de producción',
        ];
    }
}
