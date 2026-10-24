<?php

use App\Actions\Productos\ImportarProductosCsv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
IsolatedMariaDb::connect();
$datos = json_decode(fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
echo 'LISTO:'.DB::selectOne('SELECT CONNECTION_ID() AS id')->id."\n";
fflush(STDOUT);
$plan = app(ImportarProductosCsv::class)->ejecutar($datos['archivo'], true);
echo json_encode(['aplicado' => $plan['aplicado'], 'nuevos' => $plan['nuevos'], 'existentes' => $plan['existentes']], JSON_THROW_ON_ERROR)."\n";
