<?php

namespace App\Http\Requests;

use App\Actions\Produccion\ValidarParticipantesProduccion;
use App\Actions\Produccion\ValidarRegistroPan;
use App\Models\DetallePan;
use App\Models\Producto;
use App\Models\RolProduccion;
use App\Models\UnidadMedida;
use App\Rules\TurnoPermitidoUsuario;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarProduccionPanRequest extends FormRequest
{
    // Capacidad en bytes de la columna TEXT; no es un límite operativo inventado.
    private const MAX_OBSERVACION_BYTES = 65535;

    public function authorize(): bool
    {
        // Las rutas aplican auth y el Gate acceder-operacion.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $detalles = $this->input('detalles');
        if (! is_array($detalles)) {
            return;
        }

        foreach ($detalles as $indice => $detalle) {
            // No reparar estructuras malformadas ni convertir texto/arrays en cero.
            if (! is_array($detalle)) {
                continue;
            }
            $id = $detalle['producto_id'] ?? null;
            $producto = is_scalar($id) && filter_var($id, FILTER_VALIDATE_INT) !== false ? Producto::with('unidadMedida')->find($id) : null;
            if ($producto?->unidadMedida?->codigo !== UnidadMedida::LATA) {
                continue;
            }
            foreach (['coches', 'latas_adicionales'] as $campo) {
                $valor = $detalle[$campo] ?? null;
                if ($valor === null || $valor === '') {
                    $detalles[$indice][$campo] = 0;
                }
            }
        }

        $this->merge(['detalles' => $detalles]);
    }

    protected function failedValidation(Validator $validator): void
    {
        // Familia del endpoint confiable, incluso con categoria manipulada o malformada.
        $this->session()->flash('produccion_familia', 'pan');
        parent::failedValidation($validator);
    }

    public function rules(): array
    {
        return [
            'categoria' => ['required', 'string', Rule::in(['pan'])],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'turno_id' => ['bail', 'required', 'integer', 'exists:turnos,id', new TurnoPermitidoUsuario($this->user())],
            'detalles' => ['required', 'array', 'size:1', 'required_array_keys:0'],
            'detalles.*' => ['required', 'array'],
            'detalles.*.cantidad' => ['sometimes'],
            'detalles.*.coches' => ['sometimes'],
            'detalles.*.latas_adicionales' => ['sometimes'],
            'detalles.*.hora_finalizacion' => ['sometimes'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
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

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            $detalles = $this->input('detalles');
            if (! is_array($detalles)) {
                return;
            }
            foreach ($detalles as $indice => $detalle) {
                if (! is_array($detalle) || $validator->errors()->has("detalles.$indice.producto_id")) {
                    continue;
                }
                $producto = Producto::with(['unidadMedida', 'categoria'])->find($detalle['producto_id'] ?? null);
                if (! $producto || ! $producto->activo || $producto->categoria?->nombre_categorias !== 'Pan') {
                    $validator->errors()->add("detalles.$indice.producto_id", 'Debe seleccionar un producto activo de la categoría Pan.');

                    continue;
                }
                $erroresEquipo = collect($validator->errors()->keys())->contains(fn ($campo) => str_starts_with($campo, "detalles.$indice.participantes"));
                if (! $erroresEquipo && isset($detalle['participantes'])) {
                    $roles = RolProduccion::whereIn('nombre_roles_produccion', ['Maestro', 'Ayudante'])->get()->keyBy('id');
                    foreach (app(ValidarParticipantesProduccion::class)->errores($detalle['participantes'], $roles, 'Pan') as $campo => $mensajes) {
                        foreach ((array) $mensajes as $mensaje) {
                            $validator->errors()->add($campo, $mensaje);
                        }
                    }
                }
                foreach (app(ValidarRegistroPan::class)->errores($detalle, $producto) as $campo => $mensajes) {
                    foreach ($mensajes as $mensaje) {
                        $validator->errors()->add("detalles.$indice.$campo", $mensaje);
                    }
                }
            }
        }];
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
            'detalles.*.coches.min' => 'La cantidad de coches no puede ser negativa.',
            'detalles.*.hora_finalizacion.date_format' => 'La hora de finalización debe ser válida con formato HH:MM.',
            'detalles.*.latas_adicionales.min' => 'Las latas adicionales no pueden ser negativas.',
            'detalles.*.latas_adicionales.max' => 'Las latas adicionales no pueden superar '.(DetallePan::LATAS_POR_COCHE - 1).'.',
            'categoria.in' => 'Esta ruta solo permite registrar Pan.',
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
            'detalles.*.hora_finalizacion' => 'hora de finalización',
            'detalles.*.coches' => 'coches',
            'detalles.*.latas_adicionales' => 'latas adicionales',
            'detalles.*.observacion' => 'observación',
            'detalles.*.participantes' => 'participantes',
            'detalles.*.participantes.*' => 'participante',
            'detalles.*.participantes.*.empleado_id' => 'empleado',
            'detalles.*.participantes.*.rol_produccion_id' => 'rol de producción',
        ];
    }
}
