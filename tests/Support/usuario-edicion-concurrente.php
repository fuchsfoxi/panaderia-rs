<?php

use App\Actions\Usuarios\AdministrarUsuarios;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedMariaDb;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
IsolatedMariaDb::connect();
$peticion = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
fwrite(STDOUT, "LISTO\n");
try {
    $action = app(AdministrarUsuarios::class);
    if ($peticion['operacion'] === 'estado') {
        $action->estado($peticion['usuario'], false, $peticion['actor']);
    } else {
        $action->actualizar($peticion['usuario'], $peticion['datos'], $peticion['actor']);
    }
    fwrite(STDOUT, "ACTUALIZADO\n");
} catch (ValidationException $exception) {
    fwrite(STDOUT, 'PROTEGIDO:'.implode(',', array_keys($exception->errors()))."\n");
}
