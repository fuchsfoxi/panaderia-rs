<?php

namespace App\Actions\Produccion;

use App\Models\DetallePan;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\Validator;

class ValidarRegistroPan
{
    /** Valida el mismo contrato en HTTP y, de nuevo, con el producto bloqueado. */
    public function errores(array $detalle, ?Producto $producto): array
    {
        $codigo = $producto?->unidadMedida?->codigo;
        if (! in_array($codigo, [UnidadMedida::LATA, UnidadMedida::UNIDAD], true)) {
            return ['producto_id' => ['El producto Pan debe tener una unidad canónica LATA o UNIDAD.']];
        }
        $entero = ['required', function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_int($value) && ! is_string($value)) {
                $fail('El campo debe ser un entero, sin decimales ni booleanos.');
            }
        }, 'integer', 'regex:/\A[0-9]+\z/'];
        $lata = $codigo === UnidadMedida::LATA;
        $rules = $lata ? [
            'coches' => [...$entero, 'min:0', 'max:'.intdiv(DetallePan::MAX_CANTIDAD_LATAS, DetallePan::LATAS_POR_COCHE)],
            'latas_adicionales' => [...$entero, 'min:0', 'max:'.(DetallePan::LATAS_POR_COCHE - 1)],
            'hora_finalizacion' => ['required', 'date_format:H:i'],
            'cantidad' => ['missing'],
        ] : [
            'cantidad' => [...$entero, 'min:1', 'max:'.DetallePan::MAX_CANTIDAD_LATAS],
            'coches' => ['missing'], 'latas_adicionales' => ['missing'], 'hora_finalizacion' => ['missing'],
        ];
        $validator = Validator::make($detalle, $rules, [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'regex' => 'El campo :attribute debe ser un entero no negativo.',
            'min' => 'El campo :attribute no alcanza la cantidad mínima permitida.',
            'max' => 'El campo :attribute excede la cantidad máxima permitida.',
            'missing' => 'El campo :attribute no se aplica a la unidad de registro de este producto y debe omitirse.',
            'hora_finalizacion.date_format' => 'La hora de finalización debe ser válida con formato HH:MM.',
            'latas_adicionales.max' => 'Las latas adicionales no pueden superar '.(DetallePan::LATAS_POR_COCHE - 1).'.',
        ]);
        $errores = $validator->errors()->toArray();
        if ($lata) {
            if ($producto->panes_por_lata === null || $producto->panes_por_lata <= 0) {
                $errores['producto_id'][] = 'Pan por LATA requiere panes por lata positivo en el producto.';
            }
            if (! $validator->errors()->hasAny(['coches', 'latas_adicionales'])) {
                $total = (int) $detalle['coches'] * DetallePan::LATAS_POR_COCHE + (int) $detalle['latas_adicionales'];
                if ($total <= 0 || $total > DetallePan::MAX_CANTIDAD_LATAS) {
                    $errores['coches'][] = $total <= 0 ? 'Ingresa al menos un coche o una lata adicional.'
                        : 'El total de latas no puede exceder '.DetallePan::MAX_CANTIDAD_LATAS.'.';
                }
            }
        } elseif ($producto->panes_por_lata !== null) {
            $errores['producto_id'][] = 'Pan por UNIDAD debe tener panes por lata vacío en el catálogo.';
        }

        return $errores;
    }
}
