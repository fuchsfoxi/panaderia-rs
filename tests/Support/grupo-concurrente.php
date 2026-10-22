<?php

use App\Actions\Personal\AdministrarEmpleados;
use App\Actions\Personal\AdministrarGrupos;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedMariaDb;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
IsolatedMariaDb::connect();
$entrada = json_decode(fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
$connectionId = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
echo "LISTO:$connectionId\n";
fflush(STDOUT);

if ($entrada['pausar'] ?? false) {
    $pausado = false;
    DB::listen(function (QueryExecuted $query) use (&$pausado): void {
        if (! $pausado && str_contains($query->sql, 'from `empleados`') && str_contains($query->sql, 'for update')) {
            $pausado = true;
            echo "EMPLEADOS_BLOQUEADOS\n";
            fflush(STDOUT);
            if (trim(fgets(STDIN) ?: '') !== 'CONTINUAR') {
                throw new RuntimeException('El test no liberó el worker.');
            }
        }
    });
}
try {
    if ($entrada['operacion'] === 'grupo') {
        app(AdministrarGrupos::class)->guardar($entrada['datos'], $entrada['actor'], $entrada['grupo'] ?? null);
    } elseif ($entrada['operacion'] === 'estado') {
        app(AdministrarGrupos::class)->estado($entrada['grupo'], true, $entrada['actor']);
    } else {
        app(AdministrarEmpleados::class)->guardar($entrada['datos'], $entrada['actor'], $entrada['empleado']);
    }
    echo "GUARDADO\n";
} catch (ValidationException $exception) {
    echo 'RECHAZADO:'.implode(',', array_keys($exception->errors()))."\n";
}
