<?php

namespace App\Actions\Produccion;

use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use Illuminate\Support\Facades\DB;

class EliminarRegistroProduccion
{
    public function ejecutar(string $familia, int $detalleId): void
    {
        $modelo = match ($familia) {
            'pan' => DetallePan::class,
            'torta' => DetalleTorta::class,
            'bocadito' => DetalleBocadito::class,
            default => abort(404),
        };
        $produccionId = $modelo::findOrFail($detalleId)->produccion_id;

        DB::transaction(function () use ($modelo, $detalleId, $produccionId): void {
            // Mismo orden cabecera → detalle que los escritores de producción.
            $produccion = Produccion::whereKey($produccionId)->lockForUpdate()->firstOrFail();
            $detalle = $modelo::whereKey($detalleId)->where('produccion_id', $produccion->id)
                ->lockForUpdate()->firstOrFail();

            // Las FK cascade eliminan solo los pivotes de este detalle, nunca empleados.
            $detalle->delete();

            // Lecturas actuales bajo lock: contemplan también cabeceras legacy mixtas.
            foreach (['detallesPan', 'detallesTorta', 'detallesBocadito'] as $relacion) {
                if ($produccion->$relacion()->lockForUpdate()->first(['id'])) {
                    return;
                }
            }
            $produccion->delete();
        });
    }
}
