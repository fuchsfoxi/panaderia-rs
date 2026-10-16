<?php

namespace App\Actions\Usuarios;

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
            $administradores = $roles->where('nombre_roles', Rol::ADMINISTRADOR)->pluck('id');
            if (! $activo && $usuario->activo && $administradores->contains($usuario->rol_id)) {
                // Lectura actual con lock: no contar una instantánea anterior a otra transacción.
                $activos = Usuario::whereIn('rol_id', $administradores)->where('activo', true)
                    ->orderBy('id')->lockForUpdate()->get();
                if ($activos->count() <= 1) {
                    throw ValidationException::withMessages(['activo' => 'Debe permanecer al menos un Administrador activo.']);
                }
            }
            $usuario->update(['activo' => $activo]);
        }, 3);
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
