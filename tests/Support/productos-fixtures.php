<?php

use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use Tests\Support\IsolatedMariaDb;

function datosProductoDePrueba(array $fixtures, string $familia = 'Pan'): array
{
    return ['nombre_p' => 'Producto nuevo', 'categoria_id' => $fixtures['productos'][$familia]->categoria_id,
        'unidad_registro' => $familia === 'Pan' ? 'LATA' : 'UNIDAD', 'unidad_medida_id' => $fixtures['productos'][$familia]->unidad_medida_id, 'panes_por_lata' => $familia === 'Pan' ? 24 : null];
}

function datosProduccionProducto(array $fixtures, string $familia = 'Pan'): array
{
    return ['categoria' => strtolower($familia), 'fecha' => '2026-10-12', 'turno_id' => $fixtures['turno']->id,
        'detalles' => [[
            'producto_id' => $fixtures['productos'][$familia]->id, 'coches' => 1, 'latas_adicionales' => 0,
            ...($familia === 'Pan' ? ['hora_finalizacion' => '08:30'] : ['cantidad' => 3, 'forma' => 'circular']), 'observacion' => 'Historia conservada',
            'participantes' => [
                ['empleado_id' => $fixtures['empleados'][0]->id, 'rol_produccion_id' => $fixtures['roles']['Maestro']->id],
                ['empleado_id' => $fixtures['empleados'][1]->id, 'rol_produccion_id' => $fixtures['roles']['Ayudante']->id],
            ],
        ]]];
}

function historiaDeProducto(array $fixtures, string $familia): void
{
    $cabecera = Produccion::create(['fecha' => '2026-10-12', 'categoria_id' => $fixtures['productos'][$familia]->categoria_id,
        'turno_id' => $fixtures['turno']->id, 'registrado_por_usuario_id' => $fixtures['usuario']->id]);
    $modelo = match ($familia) {
        'Pan' => DetallePan::class, 'Torta' => DetalleTorta::class, default => DetalleBocadito::class
    };
    IsolatedMariaDb::detail($modelo, $cabecera, $fixtures);
}
