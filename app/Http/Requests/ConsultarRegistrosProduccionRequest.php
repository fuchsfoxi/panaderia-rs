<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ConsultarRegistrosProduccionRequest extends ConsultarHistorialProduccionRequest
{
    public function validationData(): array
    {
        // Producción solo consulta la sección activa; Historial conserva sus filtros.
        $consulta = $this->query->all();

        return [
            'categoria' => array_key_exists('categoria', $consulta) ? $consulta['categoria'] : 'pan',
            'page' => $this->query('page'),
        ];
    }

    public function rules(): array
    {
        return [
            'categoria' => ['required', 'string', Rule::in(['pan', 'torta', 'bocadito'])],
            'page' => parent::rules()['page'],
        ];
    }
}
