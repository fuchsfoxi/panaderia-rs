<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }

    public function messages(): array
    {
        return ['activo.required' => 'Indica el estado deseado del producto.', 'activo.boolean' => 'El estado del producto no es válido.'];
    }
}
