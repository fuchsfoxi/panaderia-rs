<?php

namespace App\Http\Requests;

class ConsultarDashboardRequest extends ConsultarHistorialProduccionRequest
{
    public function rules(): array
    {
        return array_intersect_key(parent::rules(), array_flip(['fecha_inicio', 'fecha_fin']));
    }
}
