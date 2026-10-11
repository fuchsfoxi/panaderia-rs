<?php

namespace App\Actions\Produccion;

use App\Models\Categoria;
use App\Models\Produccion;
use Illuminate\Validation\ValidationException;

class ResolverCabeceraProduccion
{
    public function resolver(string $fecha, int $turnoId, Categoria $categoria, int $usuarioId): Produccion
    {
        $producciones = Produccion::where('fecha', $fecha)
            ->where('categoria_id', $categoria->id)
            ->where('turno_id', $turnoId)
            ->lockForUpdate()->limit(2)->get();
        if ($producciones->count() > 1) {
            throw ValidationException::withMessages([
                'produccion' => "Existen varias sesiones de {$categoria->nombre_categorias} para esta fecha y turno. Revise las cabeceras duplicadas antes de registrar otro producto.",
            ]);
        }

        return $producciones->first() ?? Produccion::create([
            'fecha' => $fecha,
            'registrado_por_usuario_id' => $usuarioId,
            'categoria_id' => $categoria->id,
            'turno_id' => $turnoId,
        ]);
    }
}
