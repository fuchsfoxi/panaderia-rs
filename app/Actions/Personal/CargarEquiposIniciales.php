<?php

namespace App\Actions\Personal;

use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\GrupoProduccion;
use App\Models\Rol;
use App\Models\RolProduccion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;

class CargarEquiposIniciales
{
    public const EQUIPOS = [
        ['nombre' => 'Panaderos 1', 'categoria' => 'Pan', 'maestro' => 'Joshelo', 'ayudantes' => ['Joel']],
        ['nombre' => 'Panaderos 2', 'categoria' => 'Pan', 'maestro' => 'Nilson', 'ayudantes' => ['Paul']],
        ['nombre' => 'Tortas', 'categoria' => 'Torta', 'maestro' => 'Chuy', 'ayudantes' => []],
        ['nombre' => 'Bocaditos', 'categoria' => 'Bocadito', 'maestro' => 'Ibar', 'ayudantes' => ['Alberto']],
    ];

    public function __construct(private AdministrarEmpleados $empleados, private AdministrarGrupos $grupos) {}

    public function previsualizar(?int $actorId = null, ?int $josheloConfirmado = null): array
    {
        if (DB::transactionLevel() !== 0 || ! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new LogicException('La vista previa requiere una conexión MariaDB sin transacción previa.');
        }
        DB::statement('SET TRANSACTION READ ONLY');
        DB::beginTransaction();
        try {
            return $this->plan($actorId, $josheloConfirmado, false);
        } finally {
            DB::rollBack();
        }
    }

    public function aplicar(?int $actorId = null, ?int $josheloConfirmado = null): array
    {
        return DB::transaction(function () use ($actorId, $josheloConfirmado): array {
            // Releer todo antes de la primera escritura, con el mismo orden de locks de Personal.
            $plan = $this->plan($actorId, $josheloConfirmado, true);
            if ($plan['errores']) {
                throw ValidationException::withMessages(['carga' => $plan['errores']]);
            }
            $ids = [];
            foreach ($plan['empleados'] as $persona) {
                if ($persona['operacion'] === 'Reutilizar') {
                    $ids[$persona['nombre']] = $persona['id'];

                    continue;
                }
                $empleado = $this->empleados->guardar([
                    'nombre_empleados' => $persona['nombre_actual'] ?? $persona['nombre'],
                    'rol_produccion_id' => $persona['rol_id'], 'activo' => true,
                ], $plan['actor_id'], $persona['id']);
                $ids[$persona['nombre']] = $empleado->id;
            }
            foreach ($plan['grupos'] as $grupo) {
                if ($grupo['operacion'] === 'Reutilizar') {
                    continue;
                }
                $this->grupos->guardar([
                    'nombre' => $grupo['nombre'], 'categoria_id' => $grupo['categoria_id'],
                    'maestro_id' => $ids[$grupo['maestro']],
                    'integrantes' => array_map(fn ($nombre) => $ids[$nombre], $grupo['ayudantes']), 'activo' => true,
                ], $plan['actor_id']);
            }

            return $plan;
        }, 3);
    }

    private function plan(?int $actorId, ?int $josheloConfirmado, bool $bloquear): array
    {
        foreach (['empleados', 'roles_produccion', 'categorias', 'grupos_produccion', 'grupo_integrantes'] as $tabla) {
            if (! Schema::hasTable($tabla)) {
                throw ValidationException::withMessages(['esquema' => 'Falta la tabla '.$tabla.'. Revise las migraciones por separado; esta carga no las ejecuta.']);
            }
        }
        $actores = Usuario::query()->select('id', 'rol_id', 'activo')->with('rol')
            ->where('activo', true)->whereHas('rol', fn ($query) => $query->where('nombre_roles', Rol::ADMINISTRADOR))
            ->when($actorId !== null, fn ($query) => $query->whereKey($actorId));
        $actores = ($bloquear ? $actores->sharedLock() : $actores)->get();
        if ($actores->count() !== 1) {
            throw ValidationException::withMessages(['administrador' => 'Indique --administrador con el ID de un único Administrador activo.']);
        }
        $nombres = [];
        foreach (self::EQUIPOS as $equipo) {
            $nombres[$equipo['maestro']] = 'Maestro';
            foreach ($equipo['ayudantes'] as $ayudante) {
                $nombres[$ayudante] = 'Ayudante';
            }
        }
        // Bloquear grupos existentes antes de roles evita invertir el protocolo de AdministrarGrupos.
        $consultaGrupos = GrupoProduccion::whereIn(DB::raw('TRIM(nombre)'), array_column(self::EQUIPOS, 'nombre'))->orderBy('id');
        $existentesGrupos = ($bloquear ? $consultaGrupos->lockForUpdate() : $consultaGrupos)->get();
        $consultaRoles = RolProduccion::whereIn('nombre_roles_produccion', ['Maestro', 'Ayudante'])->orderBy('id');
        // Serializa cargas y altas de empleados que usan las Actions existentes, sin UNIQUE global de nombres.
        $roles = ($bloquear ? $consultaRoles->lockForUpdate() : $consultaRoles)->get();
        $consultaCategorias = Categoria::whereIn('nombre_categorias', GrupoProduccion::CATEGORIAS)->orderBy('id');
        $categorias = ($bloquear ? $consultaCategorias->sharedLock() : $consultaCategorias)->get();
        $consultaEmpleados = Empleado::whereIn(DB::raw('TRIM(nombre_empleados)'), array_keys($nombres))->orderBy('id');
        $existentes = ($bloquear ? $consultaEmpleados->lockForUpdate() : $consultaEmpleados)->get();
        $errores = $personas = $grupos = $advertencias = [];
        $rolIds = $categoriaIds = [];
        foreach (['Maestro', 'Ayudante'] as $nombre) {
            $coincidencias = $roles->filter(fn ($rol) => $this->coincideEnBase('roles_produccion', 'nombre_roles_produccion', $rol->id, $nombre, $bloquear));
            if ($coincidencias->count() !== 1 || $coincidencias->sole()->nombre_roles_produccion !== $nombre) {
                $errores[] = 'El catálogo debe contener exactamente un rol '.$nombre.'.';
            } else {
                $rolIds[$nombre] = $coincidencias->sole()->id;
            }
        }
        foreach (GrupoProduccion::CATEGORIAS as $nombre) {
            $coincidencias = $categorias->filter(fn ($categoria) => $this->coincideEnBase('categorias', 'nombre_categorias', $categoria->id, $nombre, $bloquear));
            if ($coincidencias->count() !== 1 || $coincidencias->sole()->nombre_categorias !== $nombre) {
                $errores[] = 'El catálogo debe contener exactamente una categoría '.$nombre.'.';
            } else {
                $categoriaIds[$nombre] = $coincidencias->sole()->id;
            }
        }
        foreach ($nombres as $nombre => $rol) {
            // La comparación SQL usa la collation real; no elegir first() ante homónimos.
            $coincidencias = $existentes->filter(fn ($empleado) => $this->coincideEnBase('empleados', 'nombre_empleados', $empleado->id, $nombre, $bloquear));
            $persona = ['nombre' => $nombre, 'nombre_actual' => null, 'id' => null, 'rol' => $rol,
                'rol_id' => $rolIds[$rol] ?? null, 'operacion' => 'Crear', 'rol_actual' => 'No registrado'];
            if ($coincidencias->count() > 1) {
                $persona['operacion'] = 'Conflicto';
                $errores[] = 'Homónimos de '.$nombre.' (IDs '.$coincidencias->pluck('id')->implode(', ').'): confirme identidad antes de cargar.';
            } elseif ($empleado = $coincidencias->first()) {
                $persona['id'] = $empleado->id;
                $persona['nombre_actual'] = $empleado->nombre_empleados;
                $persona['rol_actual'] = $roles->firstWhere('id', $empleado->rol_produccion_id)?->nombre_roles_produccion ?? 'Otro rol o sin rol';
                $persona['operacion'] = 'Reutilizar';
                if ($this->normalizar($empleado->nombre_empleados) !== $this->normalizar($nombre) || ! $empleado->activo) {
                    $persona['operacion'] = 'Conflicto';
                    $errores[] = $nombre.' #'.$empleado->id.': nombre no exacto o empleado inactivo; requiere revisión manual.';
                } elseif ($empleado->rol_produccion_id !== $persona['rol_id']) {
                    $confirmado = $nombre === 'Joshelo' && $josheloConfirmado === $empleado->id;
                    $persona['operacion'] = $confirmado ? 'Modificar rol' : 'Conflicto de rol';
                    if (! $confirmado) {
                        $errores[] = $nombre.' #'.$empleado->id.' tiene rol '.$persona['rol_actual'].'; se requiere '.$rol.'. No se cambiará sin confirmación específica.';
                    }
                    $afectados = GrupoProduccion::where('maestro_id', $empleado->id)
                        ->orWhereHas('integrantes', fn ($query) => $query->where('empleados.id', $empleado->id))->get(['id', 'nombre']);
                    foreach ($afectados as $afectado) {
                        $advertencias[] = 'Cambiar el rol de '.$nombre.' afecta la compatibilidad del grupo #'.$afectado->id.' «'.$afectado->nombre.'»; se conservará su configuración.';
                    }
                }
            }
            if ($nombre === 'Joshelo' && $josheloConfirmado !== null && $persona['id'] !== $josheloConfirmado) {
                $errores[] = '--confirmar-joshelo no corresponde al único Joshelo encontrado. No se asumirá otra identidad.';
            }
            $personas[$nombre] = $persona;
        }
        foreach (self::EQUIPOS as $equipo) {
            $fila = $equipo + ['id' => null, 'categoria_id' => $categoriaIds[$equipo['categoria']] ?? null, 'operacion' => 'Crear'];
            $coincidencias = $existentesGrupos->filter(fn ($grupo) => $this->coincideEnBase('grupos_produccion', 'nombre', $grupo->id, $equipo['nombre'], $bloquear));
            if ($coincidencias->count() > 1) {
                $fila['operacion'] = 'Conflicto';
                $errores[] = 'Hay varios grupos llamados '.$equipo['nombre'].'. No se elegirá uno arbitrariamente.';
            } elseif ($grupo = $coincidencias->first()) {
                $fila['id'] = $grupo->id;
                $consulta = DB::table('grupo_integrantes')->where('grupo_id', $grupo->id)->orderBy('empleado_id');
                $integrantes = ($bloquear ? $consulta->lockForUpdate() : $consulta)->pluck('empleado_id')->all();
                $esperados = array_map(fn ($nombre) => $personas[$nombre]['id'], $equipo['ayudantes']);
                sort($esperados);
                $compatible = $this->normalizar($grupo->nombre) === $this->normalizar($equipo['nombre'])
                    && $grupo->activo && $grupo->categoria_id === $fila['categoria_id']
                    && $grupo->maestro_id === $personas[$equipo['maestro']]['id'] && $integrantes === $esperados;
                $fila['operacion'] = $compatible ? 'Reutilizar' : 'Conflicto';
                if (! $compatible) {
                    $errores[] = 'El grupo '.$equipo['nombre'].' #'.$grupo->id.' tiene otra composición, categoría o estado; no se modificará automáticamente.';
                }
            }
            $grupos[] = $fila;
        }

        return ['actor_id' => $actores->sole()->id, 'empleados' => array_values($personas), 'grupos' => $grupos,
            'errores' => array_values(array_unique($errores)), 'advertencias' => array_values(array_unique($advertencias))];
    }

    private function coincideEnBase(string $tabla, string $columna, int $id, string $nombre, bool $bloquear): bool
    {
        $consulta = DB::table($tabla)->where('id', $id)->whereRaw('TRIM('.$columna.') = ?', [$nombre]);

        // En escritura conservar la lectura actual tras esperar otra carga, sin volver al snapshot anterior.
        return ($bloquear ? $consulta->sharedLock() : $consulta)->exists();
    }

    private function normalizar(string $nombre): string
    {
        return mb_strtolower(trim($nombre), 'UTF-8');
    }
}
