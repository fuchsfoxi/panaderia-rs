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
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($participantes as $posicion => $participante) {
            $empleadoId = (int) $participante['empleado_id'];
            $empleado = $existentes->get($empleadoId);
            if (! $empleado) {
                $errores["detalles.0.participantes.$posicion.empleado_id"] = 'El empleado seleccionado no existe.';
            } elseif (! $empleado->activo) {
                $errores["detalles.0.participantes.$posicion.empleado_id"] = 'El empleado está inactivo. Selecciona otro trabajador.';
            }
            if (isset($empleados[$empleadoId])) {
                $errores['detalles.0.participantes'][] = 'Un empleado no puede repetirse dentro del mismo detalle.';
            }
            $empleados[$empleadoId] = true;
            $rol = $roles->get($participante['rol_produccion_id']);
            if (! $rol) {
                $errores["detalles.0.participantes.$posicion.rol_produccion_id"] = "Para $familia solo se permiten Maestro, Ayudante y Practicante.";
            } elseif ($rol->nombre_roles_produccion === 'Maestro') {
                $maestros++;
            } elseif ($rol->nombre_roles_produccion === 'Ayudante') {
                $ayudantes++;
            }
            if ($empleado && (int) $empleado->rol_produccion_id !== (int) $participante['rol_produccion_id']) {
                $errores["detalles.0.participantes.$posicion.rol_produccion_id"] = 'El rol debe coincidir con el rol actual del empleado. Actualiza la selección.';
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
