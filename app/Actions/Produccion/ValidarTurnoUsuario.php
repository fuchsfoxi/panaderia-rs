<?php

namespace App\Actions\Produccion;

use App\Models\Turno;
use App\Models\Usuario;
use Illuminate\Validation\ValidationException;

class ValidarTurnoUsuario
{
    public function error(?Usuario $usuario, int $turnoId): ?string
    {
        if (! $usuario || $usuario->activo === false) {
            return 'La cuenta no puede registrar producción.';
        }
        if ($usuario->esAdministrador()) {
            return null;
        }
        if (! $usuario->esOperador()) {
            return 'No tienes permisos para registrar producción.';
        }
        if ($usuario->turno_id === null) {
            return 'Tu cuenta no tiene un turno asignado. Solicita su configuración al Administrador.';
        }

        return (int) $usuario->turno_id === $turnoId ? null : 'Solo puedes registrar producción en el turno asignado a tu cuenta.';
    }

    public function comprobar(int $usuarioId, int $turnoId): void
    {
        // Dentro de la transacción del escritor: estabiliza estado/rol/turno hasta el commit.
        $usuario = Usuario::with('rol')->whereKey($usuarioId)->sharedLock()->first();
        $error = $this->error($usuario, $turnoId);
        if (! $error && ! Turno::whereKey($turnoId)->exists()) {
            $error = 'El turno seleccionado no existe.';
        }
        if ($error) {
            throw ValidationException::withMessages(['turno_id' => $error]);
        }
    }
}
