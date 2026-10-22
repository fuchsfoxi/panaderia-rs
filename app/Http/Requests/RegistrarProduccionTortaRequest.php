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
            'detalles.*.hora_vitrina' => ['bail', 'required', 'date_format:H:i'],
            'detalles.*.forma' => ['required', 'string', 'max:30'],
            // SVG no permitido; formato verificado por contenido, no solo extensión.
            'detalles.*.foto' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'detalles.*.hora_vitrina.required' => 'La hora de vitrina es obligatoria.',
            'detalles.*.hora_vitrina.date_format' => 'La hora de vitrina debe tener un formato válido HH:MM (00:00 a 23:59).',
        ] + parent::messages();
    }
}
