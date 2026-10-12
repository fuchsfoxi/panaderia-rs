<?php

use App\Actions\Produccion\RegistrarProduccionBocadito;
use App\Actions\Produccion\RegistrarProduccionPan;
use App\Actions\Produccion\RegistrarProduccionTorta;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\IsolatedMariaDb;

// Worker exclusivo de las pruebas: conexión verificada a MariaDB desechable.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
IsolatedMariaDb::connect();
$entrada = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$action = match ($entrada['datos']['categoria']) {
    'pan' => RegistrarProduccionPan::class,
    'torta' => RegistrarProduccionTorta::class,
    'bocadito' => RegistrarProduccionBocadito::class,
};
fwrite(STDOUT, "LISTO\n");
fflush(STDOUT);
$produccion = app($action)->ejecutar($entrada['datos'], $entrada['usuario_id']);
fwrite(STDOUT, $produccion->id."\n");
