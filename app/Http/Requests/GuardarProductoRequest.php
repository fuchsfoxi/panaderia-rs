<?php

namespace App\Http\Requests;

use App\Actions\Productos\ValidarDatosProducto;
use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class GuardarProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización compartida se aplica en las rutas con can.
    }

    protected function prepareForValidation(): void
    {
        $this->merge(app(ValidarDatosProducto::class)->normalizar($this->all()));
    }

    public function rules(): array
    {
        $producto = $this->route('producto');

        return app(ValidarDatosProducto::class)->reglas($this->all(), $producto instanceof Producto ? $producto : null);
    }

    public function after(): array
    {
        return [fn (Validator $validator) => app(ValidarDatosProducto::class)->adicionales($validator, $this->all())];
    }

    public function messages(): array
    {
        return app(ValidarDatosProducto::class)->mensajes();
    }

    public function attributes(): array
    {
        return app(ValidarDatosProducto::class)->atributos();
    }
}
