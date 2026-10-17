<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class GuardarProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización compartida se aplica en las rutas con can.
    }

    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre_p');
        if (is_string($nombre)) {
            $this->merge(['nombre_p' => trim(preg_replace('/[\p{Z}\s]+/u', ' ', $nombre) ?? $nombre)]);
        }
        $this->merge(['panes_por_lata' => $this->input('panes_por_lata')]);
    }

    public function rules(): array
    {
        // Usar el mismo criterio que integer de Laravel, sin consultar arrays malformados.
        $categoriaId = filter_var($this->input('categoria_id'), FILTER_VALIDATE_INT);
        $esPan = $categoriaId !== false
            && Categoria::whereKey($categoriaId)->where('nombre_categorias', 'Pan')->exists();

        $producto = $this->route('producto');
        $codigo = $this->input('unidad_registro', $producto instanceof Producto ? $producto->unidadMedida?->codigo : null);
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

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('categoria_id') || $this->input('panes_por_lata') === null) {
                return;
            }
            if (Categoria::find($this->input('categoria_id'))?->nombre_categorias !== 'Pan' || $this->input('unidad_registro') === UnidadMedida::UNIDAD) {
                $validator->errors()->add('panes_por_lata', 'Los panes por lata solo se aplican a Pan registrado por latas. Déjalo vacío para cantidad directa.');
            }
        }];
    }

    public function messages(): array
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

    public function attributes(): array
    {
        return ['nombre_p' => 'nombre', 'categoria_id' => 'categoría', 'unidad_medida_id' => 'unidad de medida', 'panes_por_lata' => 'panes por lata'];
    }
}
