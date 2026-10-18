<?php

namespace App\Http\Requests;

use App\Models\RolProduccion;
use Illuminate\Validation\Rule;

class GuardarEmpleadoRequest extends GestionarEmpleadoRequest
{
    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre_empleados');
        if (is_string($nombre)) {
            $this->merge(['nombre_empleados' => trim(preg_replace('/\p{Zs}+/u', ' ', $nombre) ?? $nombre)]);
        }
    }

    public function rules(): array
    {
        return [
            'nombre_empleados' => ['bail', 'required', 'string', 'max:100', "regex:/\A[\pL\pM][\pL\pM .'\x{2019}-]*\z/u"],
            // Los homónimos son legítimos: el esquema no declara UNIQUE de nombre.
            'rol_produccion_id' => ['bail', 'required', 'integer', Rule::exists('roles_produccion', 'id')->whereIn('nombre_roles_produccion', RolProduccion::NOMBRES)],
            'activo' => ['required', 'boolean'],
        ];
    }
}
