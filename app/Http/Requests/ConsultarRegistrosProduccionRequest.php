<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ConsultarRegistrosProduccionRequest extends ConsultarHistorialProduccionRequest
{
    public function validationData(): array
    {
        // Producción solo consulta la sección activa; Historial conserva sus filtros.
        return [
            'categoria' => $this->query('categoria', 'pan'),
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
