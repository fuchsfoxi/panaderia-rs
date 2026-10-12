<?php

namespace App\Http\Requests;

class RegistrarProduccionTortaRequest extends RegistrarProduccionPorCantidadRequest
{
    protected function familia(): string
    {
        return 'torta';
    }

    public function rules(): array
    {
        return parent::rules() + [
            'detalles.*.forma' => ['required', 'string', 'max:30'],
            // SVG no permitido; formato verificado por contenido, no solo extensión.
            'detalles.*.foto' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
