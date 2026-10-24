<?php

namespace App\Actions\Personal;

use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\GrupoProduccion;
use App\Models\RolProduccion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdministrarGrupos
{
    /** Datos estructuralmente validados por el Request; composición revalidada bajo lock. */
    public function guardar(array $datos, int $actorId, ?int $grupoId = null): GrupoProduccion
    {
        return DB::transaction(function () use ($datos, $actorId, $grupoId): GrupoProduccion {
            $this->administradorActual($actorId);
            $grupo = $grupoId === null ? new GrupoProduccion : GrupoProduccion::whereKey($grupoId)->lockForUpdate()->firstOrFail();
            $grupo->fill(array_intersect_key($datos, array_flip(['nombre', 'categoria_id', 'maestro_id', 'activo'])));
            $ids = array_map('intval', $datos['integrantes'] ?? []);
            if (count($ids) !== count(array_unique($ids))) {
                throw ValidationException::withMessages(['integrantes' => 'No repitas empleados dentro del grupo.']);
            }
            $this->validarComposicion($grupo, $ids);
            $grupo->save();
            // Solo esta configuración; nunca escribir en participantes de Producción.
            $actuales = $this->integrantesActuales($grupo);
            $grupo->integrantes()->detach(array_values(array_diff($actuales, $ids)));
            $grupo->integrantes()->attach(array_values(array_diff($ids, $actuales)));

            return $grupo;
        }, 3);
    }

    public function estado(int $grupoId, bool $activo, int $actorId): void
    {
        DB::transaction(function () use ($grupoId, $activo, $actorId): void {
            $this->administradorActual($actorId);
            $grupo = GrupoProduccion::whereKey($grupoId)->lockForUpdate()->firstOrFail();
            if ($activo) {
                $ids = $this->integrantesActuales($grupo);
                $this->validarComposicion($grupo, $ids);
            }
            // Desactivar sigue permitido aunque alguna persona haya dejado de ser apta.
            $grupo->update(['activo' => $activo]);
        }, 3);
    }

    private function integrantesActuales(GrupoProduccion $grupo): array
    {
        // Lectura actual, incluso si otro editor confirmó mientras esperábamos el grupo.
        return DB::table('grupo_integrantes')->where('grupo_id', $grupo->id)
            ->orderBy('empleado_id')->lockForUpdate()->pluck('empleado_id')->all();
    }

    private function validarComposicion(GrupoProduccion $grupo, array $ids): void
    {
        $categoria = Categoria::whereKey($grupo->categoria_id)->sharedLock()->first();
        $roles = RolProduccion::whereIn('nombre_roles_produccion', RolProduccion::NOMBRES)
            ->orderBy('id')->sharedLock()->get()->keyBy('id');
        // Compatible con AdministrarEmpleados: roles antes de empleados, IDs ordenados.
        $empleados = Empleado::whereIn('id', array_merge([$grupo->maestro_id], $ids))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($empleados as $empleado) {
            $empleado->setRelation('rolProduccion', $roles->get($empleado->rol_produccion_id));
        }
        $grupo->setRelation('categoria', $categoria);
        $grupo->setRelation('maestro', $empleados->get($grupo->maestro_id));
        $grupo->setRelation('integrantes', $empleados->only($ids)->values());
        $errores = $grupo->problemasComposicion();
        if ($grupo->integrantes->count() !== count($ids)) {
            $errores['integrantes'] = 'Alguno de los integrantes seleccionados ya no existe.';
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    private function administradorActual(int $actorId): void
    {
        $actor = Usuario::with('rol')->whereKey($actorId)->sharedLock()->first();
        abort_unless($actor?->activo && $actor->esAdministrador(), 403);
    }
}
