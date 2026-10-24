<?php

namespace App\Actions\Productos;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ValidarDatosProducto
{
    public function normalizar(array $datos): array
    {
        if (isset($datos['nombre_p']) && is_string($datos['nombre_p'])) {
            $datos['nombre_p'] = trim(preg_replace('/[\p{Z}\s]+/u', ' ', $datos['nombre_p']) ?? $datos['nombre_p']);
        }
        $datos['panes_por_lata'] ??= null;

        return $datos;
    }

    public function reglas(array $datos, ?Producto $producto = null): array
    {
        // Usar el mismo criterio que integer de Laravel, sin consultar arrays malformados.
        $categoriaId = filter_var(($datos['categoria_id'] ?? null), FILTER_VALIDATE_INT);
        $esPan = $categoriaId !== false
            && Categoria::whereKey($categoriaId)->where('nombre_categorias', 'Pan')->exists();

        $codigo = ($datos['unidad_registro'] ?? $producto?->unidadMedida?->codigo);
        $esLata = $esPan && $codigo === UnidadMedida::LATA;

        return [
            'nombre_p' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->whereIn('nombre_categorias', ['Pan', 'Torta', 'Bocadito'])],
            'unidad_registro' => ['sometimes', 'required', 'string', Rule::in([UnidadMedida::LATA, UnidadMedida::UNIDAD])],
            'unidad_medida_id' => ['sometimes', 'integer', 'exists:unidades_medida,id'],
            'panes_por_lata' => [Rule::requiredIf($esLata), 'nullable', function (string $attribute, mixed $value, Closure $fail): void {
                if ((! is_int($value) && ! is_string($value)) || ! preg_match('/^[0-9]+$/D', (string) $value)) {
                    $fail('Los panes por lata deben ser un número entero positivo.');
                }
            }, 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    public function adicionales(Validator $validator, array $datos): void
    {
        if ($validator->errors()->has('categoria_id') || ($datos['panes_por_lata'] ?? null) === null) {
            return;
        }
        if (Categoria::find($datos['categoria_id'])?->nombre_categorias !== 'Pan' || ($datos['unidad_registro'] ?? null) === UnidadMedida::UNIDAD) {
            $validator->errors()->add('panes_por_lata', 'Los panes por lata solo se aplican a Pan registrado por latas. Déjalo vacío para cantidad directa.');
        }
    }

    public function mensajes(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'nombre_p.max' => 'El nombre no puede superar 100 caracteres.',
            'nombre_p.not_regex' => 'El nombre contiene caracteres no válidos.',
            'categoria_id.exists' => 'Selecciona una categoría disponible: Pan, Torta o Bocadito.',
            'unidad_medida_id.exists' => 'La unidad de medida seleccionada no existe.',
            'panes_por_lata.required' => 'Debes indicar cuántos panes contiene una lata.',
            'panes_por_lata.min' => 'Los panes por lata deben ser mayores que 0.',
            'panes_por_lata.max' => 'Los panes por lata no pueden superar 2147483647.',
        ];
    }

    public function atributos(): array
    {
        return ['nombre_p' => 'nombre', 'categoria_id' => 'categoría', 'unidad_medida_id' => 'unidad de medida', 'panes_por_lata' => 'panes por lata'];
    }
}
