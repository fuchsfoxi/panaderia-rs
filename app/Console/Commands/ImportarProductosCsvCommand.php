<?php

namespace App\Console\Commands;

use App\Actions\Productos\ImportarProductosCsv;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use LogicException;

class ImportarProductosCsvCommand extends Command
{
    protected $signature = 'productos:importar-csv {archivo : Ruta al CSV UTF-8}
        {--dry-run : Validar y mostrar sin guardar (predeterminado)}
        {--aplicar : Guardar solo si todas las filas son válidas; requiere respaldo y autorización operativa previos}';

    protected $description = 'Previsualiza o importa productos sin sobrescribir el catálogo existente';

    public function handle(ImportarProductosCsv $importar): int
    {
        if ($this->option('dry-run') && $this->option('aplicar')) {
            $this->error('Use --dry-run o --aplicar, nunca ambos.');

            return self::FAILURE;
        }
        try {
            $plan = $importar->ejecutar($this->argument('archivo'));
            $this->info('Vista previa READ ONLY. Duplicados: nombre y categoría según la collation de MariaDB.');
            $this->mostrar($plan);
            if ($plan['rechazados'] > 0) {
                $this->error('Corrige las filas rechazadas. No se ha modificado ningún dato.');

                return self::FAILURE;
            }
            if (! $this->option('aplicar')) {
                $this->info('Solo vista previa. Para guardar se requiere respaldo, autorización previa y --aplicar.');

                return self::SUCCESS;
            }
            $plan = $importar->ejecutar($this->argument('archivo'), true);
            $this->mostrar($plan);
            if (! $plan['aplicado']) {
                $this->error('La revalidación detectó errores. Ningún dato modificado.');

                return self::FAILURE;
            }
            $this->info('Importación completada en una única transacción.');

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->error($mensaje);
                }
            }
        } catch (QueryException) {
            $this->error('No se pudo consultar o guardar el catálogo. Cualquier escritura de esta importación se revirtió.');
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());
        }

        return self::FAILURE;
    }

    private function mostrar(array $plan): void
    {
        $this->table(['Fila', 'Nombre', 'Estado', 'Detalle'], array_map(fn ($fila) => [
            $fila['numero'], $fila['nombre'], $fila['estado'], $fila['mensaje'],
        ], $plan['filas']));
        $this->line('Nuevos: '.$plan['nuevos'].' · Existentes: '.$plan['existentes'].' · Rechazados: '.$plan['rechazados']);
    }
}
