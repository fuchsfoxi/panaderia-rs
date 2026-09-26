<?php

namespace Database\Seeders;

use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class UnidadMedidaSeeder extends Seeder
{
    public function run(): void
    {
        // equivalencia_unidades = NULL significa "conversion todavia no confirmada".
        // No se setea en 1.00 a proposito: 1.00 seria un valor falso que el
        // dashboard multiplicaria en silencio y daria reportes erroneos.
        // El valor es relativo a la unidad base ('unidad' = 1).
        // Cuando se confirmen los numeros reales, actualizar lata y coche con:
        //   UPDATE unidades_medida SET equivalencia_unidades = <n> WHERE nombre_unidades_medida = '...';

        $unidades = [
            ['nombre_unidades_medida' => 'unidad', 'equivalencia_unidades' => 1.00],
            ['nombre_unidades_medida' => 'lata', 'equivalencia_unidades' => null],
            ['nombre_unidades_medida' => 'coche', 'equivalencia_unidades' => null],
        ];

        foreach ($unidades as $unidad) {
            // firstOrCreate (no updateOrCreate) para no pisar las equivalencias
            // que alguien haya cargado despues.
            UnidadMedida::firstOrCreate(
                ['nombre_unidades_medida' => $unidad['nombre_unidades_medida']],
                ['equivalencia_unidades' => $unidad['equivalencia_unidades']]
            );
        }
    }
}
