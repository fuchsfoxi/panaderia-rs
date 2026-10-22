<?php

namespace App\Console\Commands;

use App\Actions\Personal\CargarEquiposIniciales;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use LogicException;

class CargarEquiposInicialesCommand extends Command
{
    protected $signature = 'personal:cargar-equipos-iniciales
        {--dry-run : Mostrar la vista previa en READ ONLY, sin guardar}
        {--aplicar : Aplicar el plan completo; requiere autorización operativa previa}
        {--administrador= : ID de Administrador activo; se deriva solo si hay uno}
        {--confirmar-joshelo= : ID cuya identidad y promoción de Joshelo a Maestro fueron confirmadas}';

    protected $description = 'Carga puntual e idempotente de los siete empleados y cuatro equipos iniciales';

    public function handle(CargarEquiposIniciales $carga): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Esta carga puntual solo está habilitada en local o testing.');

            return self::FAILURE;
        }
        if ($this->option('dry-run') && $this->option('aplicar')) {
            $this->error('Use --dry-run o --aplicar, nunca ambos.');

            return self::FAILURE;
        }
        $ids = [];
        foreach (['administrador', 'confirmar-joshelo'] as $opcion) {
            $valor = $this->option($opcion);
            $ids[$opcion] = $valor === null ? null : filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($ids[$opcion] === false) {
                $this->error('--'.$opcion.' requiere un ID entero positivo.');

                return self::FAILURE;
            }
        }
        try {
            $plan = $carga->previsualizar($ids['administrador'], $ids['confirmar-joshelo']);
            $this->info('Vista previa READ ONLY — Administrador #'.$plan['actor_id']);
            $this->table(['Empleado', 'ID', 'Rol actual', 'Rol previsto', 'Operación'], array_map(fn ($persona) => [
                $persona['nombre_actual'] ?? $persona['nombre'], $persona['id'] ?? 'Nuevo', $persona['rol_actual'], $persona['rol'], $persona['operacion'],
            ], $plan['empleados']));
            $this->table(['Grupo', 'Categoría', 'Maestro', 'Ayudantes', 'Operación'], array_map(fn ($grupo) => [
                $grupo['nombre'], $grupo['categoria'], $grupo['maestro'], implode(', ', $grupo['ayudantes']) ?: 'Ninguno', $grupo['operacion'],
            ], $plan['grupos']));
            foreach ($plan['advertencias'] as $advertencia) {
                $this->warn($advertencia);
            }
            if ($plan['errores']) {
                foreach ($plan['errores'] as $error) {
                    $this->error($error);
                }
                $this->error('Carga bloqueada. No se ha modificado ningún dato.');

                return self::FAILURE;
            }
            if (! $this->option('aplicar')) {
                $this->info('Solo vista previa. Ningún dato modificado. La carga real requiere autorización previa y --aplicar.');

                return self::SUCCESS;
            }
            $carga->aplicar($ids['administrador'], $ids['confirmar-joshelo']);
            $this->info('Carga completada en una única transacción. Las coincidencias válidas se reutilizaron sin reescribirlas.');

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->error($mensaje);
                }
            }
        } catch (QueryException) {
            $this->error('No se pudo completar la consulta/carga. Las escrituras de la carga se revirtieron; revise la conexión o el esquema.');
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());
        }

        return self::FAILURE;
    }
}
