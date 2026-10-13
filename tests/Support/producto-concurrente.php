<?php

use App\Actions\Produccion\RegistrarProduccionBocadito;
use App\Actions\Produccion\RegistrarProduccionPan;
use App\Actions\Produccion\RegistrarProduccionTorta;
use App\Actions\Productos\ActualizarProducto;
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
fwrite(STDOUT, "LISTO:$connectionId\n");
fflush(STDOUT);

if ($entrada['pausar']) {
    $pausado = false;
    DB::listen(function (QueryExecuted $consulta) use (&$pausado): void {
        if (! $pausado && str_contains($consulta->sql, 'from `productos`') && str_contains($consulta->sql, 'for update')) {
            $pausado = true;
            fwrite(STDOUT, "PRODUCTO_BLOQUEADO\n");
            fflush(STDOUT);
            if (trim(fgets(STDIN) ?: '') !== 'CONTINUAR') {
                throw new RuntimeException('El test no liberó el worker bloqueado.');
            }
        }
    });
}

try {
    if ($entrada['operacion'] === 'editar') {
        app(ActualizarProducto::class)->ejecutar($entrada['producto_id'], $entrada['datos']);
    } else {
        $action = match ($entrada['datos']['categoria']) {
            'pan' => RegistrarProduccionPan::class,
            'torta' => RegistrarProduccionTorta::class,
            'bocadito' => RegistrarProduccionBocadito::class,
        };
        app($action)->ejecutar($entrada['datos'], $entrada['usuario_id']);
    }
    echo json_encode(['resultado' => 'GUARDADO']).PHP_EOL;
} catch (ValidationException $exception) {
    echo json_encode(['resultado' => 'RECHAZADO', 'campos' => array_keys($exception->errors())]).PHP_EOL;
}
