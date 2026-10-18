<?php

namespace App\Actions\Usuarios;

use App\Models\Empleado;
use App\Models\Rol;
use App\Models\Turno;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdministrarUsuarios
{
    public function crear(array $datos, int $actorId): Usuario
    {
        $hash = Hash::make($datos['password']);

        return DB::transaction(function () use ($datos, $actorId, $hash) {
            $roles = $this->bloquearRoles();
            $this->administradorActual($actorId);
            $rol = $roles->firstWhere('id', (int) $datos['rol_id']);
            if (! $rol) {
                throw ValidationException::withMessages(['rol_id' => 'Selecciona un rol válido.']);
            }
            $turnoId = null;
            if ($rol->nombre_roles === Rol::OPERADOR) {
                $turnoId = Turno::whereKey($datos['turno_id'] ?? null)->sharedLock()->value('id');
                if (! $turnoId) {
                    throw ValidationException::withMessages(['turno_id' => 'El Operador necesita un turno existente.']);
                }
            }

            return Usuario::create([
                'username' => $datos['username'], 'password_hash' => $hash,
                'rol_id' => $rol->id, 'turno_id' => $turnoId, 'empleado_id' => null, 'activo' => true,
            ]);
        }, 3);
    }

    public function estado(int $usuarioId, bool $activo, int $actorId): void
    {
        DB::transaction(function () use ($usuarioId, $activo, $actorId) {
            $roles = $this->bloquearRoles();
            $this->administradorActual($actorId);
            $usuario = Usuario::whereKey($usuarioId)->lockForUpdate()->firstOrFail();
            if (! $activo) {
                $this->protegerUltimoAdministrador($usuario, $roles, 'activo');
            }
            $usuario->update(['activo' => $activo]);
        }, 3);
    }

    public function actualizar(int $usuarioId, array $datos, int $actorId): Usuario
    {
        return DB::transaction(function () use ($usuarioId, $datos, $actorId): Usuario {
            // Mismo punto de serialización que crear, estado y contraseña.
            $roles = $this->bloquearRoles();
            $this->administradorActual($actorId);
            $usuario = Usuario::whereKey($usuarioId)->lockForUpdate()->firstOrFail();
            $rol = $roles->firstWhere('id', (int) $datos['rol_id']);
            if (! $rol) {
                throw ValidationException::withMessages(['rol_id' => 'Selecciona un rol válido.']);
            }
            if ($rol->nombre_roles !== Rol::ADMINISTRADOR) {
                $this->protegerUltimoAdministrador($usuario, $roles, 'rol_id');
            }
            $turnoId = null;
            if ($rol->nombre_roles === Rol::OPERADOR) {
                $turnoId = Turno::whereKey($datos['turno_id'] ?? null)->sharedLock()->value('id');
                if (! $turnoId) {
                    throw ValidationException::withMessages(['turno_id' => 'El Operador necesita un turno existente.']);
                }
            }
            // Omitir el campo conserva el vínculo; enviar NULL lo retira explícitamente.
            $empleadoId = array_key_exists('empleado_id', $datos) ? $datos['empleado_id'] : $usuario->empleado_id;
            if ($empleadoId !== null) {
                if (! Empleado::whereKey($empleadoId)->sharedLock()->first(['id'])) {
                    throw ValidationException::withMessages(['empleado_id' => 'El empleado seleccionado no existe.']);
                }
                if (Usuario::where('empleado_id', $empleadoId)->where('id', '!=', $usuario->id)->lockForUpdate()->first(['id'])) {
                    throw ValidationException::withMessages(['empleado_id' => 'Este empleado ya está vinculado a otra cuenta.']);
                }
            }
            // No actualizar estado, contraseña ni datos laborales/históricos.
            $usuario->update(['username' => $datos['username'], 'rol_id' => $rol->id, 'turno_id' => $turnoId, 'empleado_id' => $empleadoId]);

            return $usuario;
        }, 3);
    }

    private function protegerUltimoAdministrador(Usuario $usuario, Collection $roles, string $campo): void
    {
        $administradores = $roles->where('nombre_roles', Rol::ADMINISTRADOR)->pluck('id');
        if ($usuario->activo && $administradores->contains($usuario->rol_id)) {
            // Lectura actual bajo lock, compartida por desactivación y cambio de rol.
            $activos = Usuario::whereIn('rol_id', $administradores)->where('activo', true)
                ->orderBy('id')->lockForUpdate()->get();
            if ($activos->count() <= 1) {
                throw ValidationException::withMessages([$campo => 'Debe permanecer al menos un Administrador activo.']);
            }
        }
    }

    public function password(int $usuarioId, string $password, int $actorId): void
    {
        $hash = Hash::make($password);
        DB::transaction(function () use ($usuarioId, $hash, $actorId) {
            $this->bloquearRoles();
            $this->administradorActual($actorId);
            $usuario = Usuario::whereKey($usuarioId)->lockForUpdate()->firstOrFail();
            $usuario->update(['password_hash' => $hash]);
        }, 3);
    }

    private function bloquearRoles(): Collection
    {
        // Las escrituras de cuentas comparten este orden para serializar el último Administrador.
        return Rol::whereIn('nombre_roles', [Rol::ADMINISTRADOR, Rol::OPERADOR])->orderBy('id')->lockForUpdate()->get();
    }

    private function administradorActual(int $actorId): void
    {
        $actor = Usuario::with('rol')->whereKey($actorId)->lockForUpdate()->first();
        abort_unless($actor?->activo && $actor->esAdministrador(), 403);
    }
}
