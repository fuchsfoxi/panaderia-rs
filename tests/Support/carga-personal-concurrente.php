<?php

use App\Actions\Personal\CargarEquiposIniciales;
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
app(CargarEquiposIniciales::class)->aplicar($datos['actor'], $datos['joshelo']);
echo "APLICADO\n";
