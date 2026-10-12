<?php

namespace App\Actions\Produccion;

use App\Models\Empleado;
use Illuminate\Database\Eloquent\Collection;

class ValidarParticipantesProduccion
{
    public function errores(array $participantes, Collection $roles, string $familia): array
    {
        $errores = [];
        $empleados = [];
        $maestros = 0;
        $ayudantes = 0;
        $existentes = Empleado::whereIn('id', array_column($participantes, 'empleado_id'))
            ->orderBy('id')->lockForUpdate()->pluck('id')->flip();
        foreach ($participantes as $posicion => $participante) {
            $empleadoId = (int) $participante['empleado_id'];
            if (! $existentes->has($empleadoId)) {
                $errores["detalles.0.participantes.$posicion.empleado_id"] = 'El empleado seleccionado no existe.';
            }
            if (isset($empleados[$empleadoId])) {
                $errores['detalles.0.participantes'][] = 'Un empleado no puede repetirse dentro del mismo detalle.';
            }
            $empleados[$empleadoId] = true;
            $rol = $roles->get($participante['rol_produccion_id']);
            if (! $rol) {
                $errores["detalles.0.participantes.$posicion.rol_produccion_id"] = "Para $familia solo se permiten los roles Maestro y Ayudante.";
            } elseif ($rol->nombre_roles_produccion === 'Maestro') {
                $maestros++;
            } else {
                $ayudantes++;
            }
        }
        if ($maestros !== 1) {
            $errores['detalles.0.participantes'][] = "Cada detalle de $familia debe tener exactamente un Maestro.";
        }
        if ($ayudantes < 1) {
            $errores['detalles.0.participantes'][] = "Cada detalle de $familia debe tener al menos un Ayudante.";
        }

        return $errores;
    }
}
