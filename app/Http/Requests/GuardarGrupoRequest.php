<?php

namespace App\Http\Requests;

use App\Models\GrupoProduccion;
use Illuminate\Validation\Rule;

class GuardarGrupoRequest extends GestionarGrupoRequest
{
    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre');
        if (is_string($nombre)) {
            $this->merge(['nombre' => trim(preg_replace('/\p{Zs}+/u', ' ', $nombre) ?? $nombre)]);
        }
        // Un formulario sin checkboxes seleccionados representa cero Ayudantes.
        if (! $this->exists('integrantes')) {
            $this->merge(['integrantes' => []]);
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => ['bail', 'required', 'string', 'max:100', 'not_regex:/[\p{Cc}\p{Cf}]/u'],
            'categoria_id' => ['bail', 'required', 'integer', Rule::exists('categorias', 'id')->whereIn('nombre_categorias', GrupoProduccion::CATEGORIAS)],
            'maestro_id' => ['bail', 'required', 'integer', 'exists:empleados,id'],
            'integrantes' => ['present', 'array', 'list'],
            'integrantes.*' => ['bail', 'required', 'integer', 'distinct', 'exists:empleados,id'],
            'activo' => ['required', 'boolean'],
        ];
    }
}
