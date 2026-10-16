<?php

use App\Actions\Usuarios\AdministrarUsuarios;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedMariaDb;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
IsolatedMariaDb::connect();
$actor = (int) trim(stream_get_contents(STDIN));
fwrite(STDOUT, "LISTO\n");
try {
    app(AdministrarUsuarios::class)->estado($actor, false, $actor);
    fwrite(STDOUT, "DESACTIVADO\n");
} catch (ValidationException) {
    fwrite(STDOUT, "PROTEGIDO\n");
}
