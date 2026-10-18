<?php

namespace App\Actions\Personal;

use App\Models\Empleado;
use App\Models\RolProduccion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdministrarEmpleados
{
    public function guardar(array $datos, int $actorId, ?int $empleadoId = null): Empleado
    {
        return DB::transaction(function () use ($datos, $actorId, $empleadoId): Empleado {
            $this->administradorActual($actorId);
            $rol = RolProduccion::whereKey($datos['rol_produccion_id'])
                ->whereIn('nombre_roles_produccion', RolProduccion::NOMBRES)->sharedLock()->first();
            if (! $rol) {
                throw ValidationException::withMessages(['rol_produccion_id' => 'Selecciona Maestro, Ayudante o Practicante.']);
            }
            $empleado = $empleadoId === null ? new Empleado : Empleado::whereKey($empleadoId)->lockForUpdate()->firstOrFail();
            // Nunca modificar cuentas, cargo legacy, número ni pivotes históricos.
            $empleado->fill([
                'nombre_empleados' => $datos['nombre_empleados'],
                'rol_produccion_id' => $rol->id,
                'activo' => (bool) $datos['activo'],
            ])->save();

            return $empleado;
        }, 3);
    }

    public function estado(int $empleadoId, bool $activo, int $actorId): void
    {
        DB::transaction(function () use ($empleadoId, $activo, $actorId): void {
            $this->administradorActual($actorId);
            Empleado::whereKey($empleadoId)->lockForUpdate()->firstOrFail()->update(['activo' => $activo]);
        }, 3);
    }

    private function administradorActual(int $actorId): void
    {
        $actor = Usuario::with('rol')->whereKey($actorId)->sharedLock()->first();
        abort_unless($actor?->activo && $actor->esAdministrador(), 403);
    }
}
