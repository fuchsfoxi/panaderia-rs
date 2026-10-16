<?php

namespace Database\Seeders;

use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UnidadMedidaSeeder extends Seeder
{
    public function run(): void
    {
        $errores = [];
        foreach ([UnidadMedida::LATA => 'Lata', UnidadMedida::UNIDAD => 'Unidad'] as $codigo => $nombre) {
            $error = DB::transaction(function () use ($codigo, $nombre): ?string {
                // El nombre se inspecciona solo para adoptar datos legacy, nunca para resolver en runtime.
                if (UnidadMedida::where('codigo', $codigo)->exists()) {
                    return null;
                }
                $candidatas = UnidadMedida::whereRaw('LOWER(TRIM(nombre_unidades_medida)) = ?', [strtolower($nombre)])
                    ->lockForUpdate()->get();
                if ($candidatas->count() > 1 || ($candidatas->count() === 1
                    && ($candidatas->sole()->codigo !== null || (float) $candidatas->sole()->equivalencia_unidades !== 1.0))) {
                    return "No se asignó $codigo: catálogo histórico ambiguo o incompatible; revisar manualmente sin reasignar productos.";
                }
                if ($candidatas->isEmpty()) {
                    UnidadMedida::create(['codigo' => $codigo, 'nombre_unidades_medida' => $nombre, 'equivalencia_unidades' => 1]);
                } else {
                    $candidatas->sole()->update(['codigo' => $codigo]);
                }

                return null;
            });
            if ($error !== null) {
                $errores[] = $error;
            }
        }
        if ($errores) {
            throw new RuntimeException(implode(' ', $errores));
        }
    }
}
