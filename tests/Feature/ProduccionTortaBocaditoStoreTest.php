<?php

use App\Actions\Produccion\RegistrarProduccionBocadito;
use App\Actions\Produccion\RegistrarProduccionTorta;
use App\Models\Categoria;
use App\Models\DetalleBocadito;
use App\Models\DetalleBocaditoEmpleado;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\DetalleTortaEmpleado;
use App\Models\Produccion;
use App\Models\RolProduccion;
use App\Models\Turno;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para probar las tres familias en MariaDB aislada.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    Categoria::create(['nombre_categorias' => 'Familia anterior ficticia']);
    RolProduccion::create(['nombre_roles_produccion' => 'Rol anterior ficticio']);
    Turno::create(['nombre_turnos' => 'Turno anterior ficticio']);
    $this->fixtures = IsolatedMariaDb::fixtures();
    $this->withoutVite();
    Storage::fake('public');
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

function cantidadPayload(array $fixtures, string $familia): array
{
    return [
        'categoria' => strtolower($familia), 'fecha' => '2026-10-10', 'turno_id' => $fixtures['turno']->id,
        'detalles' => [[
            'producto_id' => $fixtures['productos'][$familia]->id, 'cantidad' => 4,
            'forma' => 'circular', 'observacion' => 'Nota de lote '.$familia,
            'participantes' => [
                ['empleado_id' => $fixtures['empleados'][0]->id, 'rol_produccion_id' => $fixtures['roles']['Maestro']->id],
                ['empleado_id' => $fixtures['empleados'][1]->id, 'rol_produccion_id' => $fixtures['roles']['Ayudante']->id],
            ],
        ]],
    ];
}

function modeloCantidad(string $familia): string
{
    return $familia === 'Torta' ? DetalleTorta::class : DetalleBocadito::class;
}

function rutaCantidad(string $familia): string
{
    return route('produccion.'.strtolower($familia).'.store');
}

test('registra lote por cantidad con registrador autenticado y pivots reales', function (string $familia) {
    $payload = cantidadPayload($this->fixtures, $familia);
    $payload['registrado_por_usuario_id'] = 999999; // El cliente no decide el autor.
    $response = $this->actingAs($this->fixtures['usuario'])->followingRedirects()
        ->post(rutaCantidad($familia), $payload)->assertOk()->assertSessionHasNoErrors()
        ->assertSeeText('Producto de '.$familia.' registrado correctamente.')
        ->assertSeeText('4 unidades')->assertSeeText('Nota de lote '.$familia);
    $cabecera = Produccion::sole();
    $detalle = modeloCantidad($familia)::sole();
    expect($cabecera->categoria_id)->toBe($this->fixtures['productos'][$familia]->categoria_id)
        ->and($cabecera->turno_id)->toBe($this->fixtures['turno']->id)
        ->and($cabecera->registrado_por_usuario_id)->toBe($this->fixtures['usuario']->id)
        ->and($detalle->cantidad)->toBe(4)->and($detalle->observacion)->toBe('Nota de lote '.$familia)
        ->and($detalle->empleados)->toHaveCount(2)
        ->and($response->viewData('categoriaSeleccionada'))->toBe(strtolower($familia));
    foreach ($payload['detalles'][0]['participantes'] as $participante) {
        $this->assertDatabaseHas('detalle_'.strtolower($familia).'_empleado', [
            'detalle_'.strtolower($familia).'_id' => $detalle->id,
        ] + $participante);
    }
    if ($familia === 'Torta') {
        expect($detalle->forma)->toBe('circular')->and($detalle->foto)->toBeNull();
        $response->assertSeeText('Forma: circular');
    }
    expect(DetallePan::count())->toBe(0);
})->with(['Torta', 'Bocadito']);

test('rechaza referencias cantidades y estructuras inválidas sin escrituras', function (string $familia, string $caso, string $campo) {
    $payload = cantidadPayload($this->fixtures, $familia);
    switch ($caso) {
        case 'otra categoría': $payload['detalles'][0]['producto_id'] = $this->fixtures['productos']['Pan']->id;
            break;
        case 'producto inexistente': $payload['detalles'][0]['producto_id'] = 999999;
            break;
        case 'inactivo': $this->fixtures['productos'][$familia]->update(['activo' => false]);
            break;
        case 'cero': $payload['detalles'][0]['cantidad'] = 0;
            break;
        case 'negativo': $payload['detalles'][0]['cantidad'] = -1;
            break;
        case 'decimal': $payload['detalles'][0]['cantidad'] = 1.5;
            break;
        case 'exceso': $payload['detalles'][0]['cantidad'] = 2147483648;
            break;
        case 'cantidad array': $payload['detalles'][0]['cantidad'] = ['malformado'];
            break;
        case 'turno inexistente': $payload['turno_id'] = 999999;
            break;
        case 'turno ausente': unset($payload['turno_id']);
            break;
        case 'fecha inválida': $payload['fecha'] = '2026-02-30';
            break;
        case 'empleado inexistente': $payload['detalles'][0]['participantes'][0]['empleado_id'] = 999999;
            break;
        case 'rol inexistente': $payload['detalles'][0]['participantes'][0]['rol_produccion_id'] = 999999;
            break;
        case 'observación array': $payload['detalles'][0]['observacion'] = ['malformado'];
            break;
        case 'observación larga': $payload['detalles'][0]['observacion'] = str_repeat('á', 32768);
            break;
        case 'detalles texto': $payload['detalles'] = 'malformado';
            break;
        case 'detalle texto': $payload['detalles'][0] = 'malformado';
            break;
        case 'participantes texto': $payload['detalles'][0]['participantes'] = 'malformado';
            break;
        case 'índice ajeno': $payload['detalles'] = [1 => $payload['detalles'][0]];
            break;
        case 'familia array':
            $payload['categoria'] = ['malformado'];
            break;
        case 'familia manipulada': $payload['categoria'] = 'pan';
            break;
    }
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))
        ->post(rutaCantidad($familia), $payload)->assertRedirect()->assertSessionHasErrors($campo);
    $response = $this->withCookie(config('session.cookie'), app('session.store')->getId())
        ->get(route('produccion.index'))->assertOk()->assertSeeText('Revisa los campos indicados');
    expect($response->viewData('categoriaSeleccionada'))->toBe(strtolower($familia))
        ->and(Produccion::count())->toBe(0)->and(modeloCantidad($familia)::count())->toBe(0);
})->with(['Torta', 'Bocadito'])->with([
    ['otra categoría', 'detalles.0.producto_id'], ['producto inexistente', 'detalles.0.producto_id'],
    ['inactivo', 'detalles.0.producto_id'], ['cero', 'detalles.0.cantidad'], ['negativo', 'detalles.0.cantidad'],
    ['decimal', 'detalles.0.cantidad'], ['exceso', 'detalles.0.cantidad'], ['cantidad array', 'detalles.0.cantidad'],
    ['turno inexistente', 'turno_id'], ['turno ausente', 'turno_id'], ['fecha inválida', 'fecha'],
    ['empleado inexistente', 'detalles.0.participantes.0.empleado_id'], ['rol inexistente', 'detalles.0.participantes.0.rol_produccion_id'],
    ['observación array', 'detalles.0.observacion'], ['observación larga', 'detalles.0.observacion'],
    ['detalles texto', 'detalles'], ['detalle texto', 'detalles.0'], ['participantes texto', 'detalles.0.participantes'],
    ['índice ajeno', 'detalles'], ['familia manipulada', 'categoria'], ['familia array', 'categoria'],
]);

test('cada familia exige exactamente un Maestro Ayudantes y empleados distintos', function (string $familia, string $caso) {
    $payload = cantidadPayload($this->fixtures, $familia);
    $participantes = &$payload['detalles'][0]['participantes'];
    switch ($caso) {
        case 'sin Maestro': $participantes[0]['rol_produccion_id'] = $this->fixtures['roles']['Ayudante']->id;
            break;
        case 'dos Maestros': $participantes[1]['rol_produccion_id'] = $this->fixtures['roles']['Maestro']->id;
            break;
        case 'sin Ayudante': $participantes = [$participantes[0]];
            break;
        case 'duplicado': $participantes[1]['empleado_id'] = $participantes[0]['empleado_id'];
            break;
        case 'otro rol': $participantes[1]['rol_produccion_id'] = $this->fixtures['roles']['Practicante']->id;
            break;
    }
    $this->actingAs($this->fixtures['usuario'])->post(rutaCantidad($familia), $payload)
        ->assertSessionHasErrors($caso === 'otro rol' ? 'detalles.0.participantes.1.rol_produccion_id' : 'detalles.0.participantes');
    expect(Produccion::count())->toBe(0)->and(DB::table('detalle_'.strtolower($familia).'_empleado')->count())->toBe(0);
})->with(['Torta', 'Bocadito'])->with(['sin Maestro', 'dos Maestros', 'sin Ayudante', 'duplicado', 'otro rol']);

test('reutiliza sesión y separa cabeceras por fecha turno y familia', function (string $familia) {
    $payload = cantidadPayload($this->fixtures, $familia);
    $this->actingAs($this->fixtures['usuario']);
    $this->post(rutaCantidad($familia), $payload)->assertSessionHasNoErrors();
    $payload['detalles'][0]['cantidad'] = 7;
    $payload['detalles'][0]['observacion'] = 'Segundo lote';
    $this->post(rutaCantidad($familia), $payload)->assertSessionHasNoErrors();
    expect(Produccion::count())->toBe(1)->and(modeloCantidad($familia)::orderBy('id')->pluck('cantidad')->all())->toBe([4, 7]);
    $payload['fecha'] = '2026-10-11';
    $this->post(rutaCantidad($familia), $payload)->assertSessionHasNoErrors();
    $payload['turno_id'] = Turno::create(['nombre_turnos' => 'Noche'])->id;
    $this->post(rutaCantidad($familia), $payload)->assertSessionHasNoErrors();
    expect(Produccion::count())->toBe(3)->and(modeloCantidad($familia)::count())->toBe(4);
})->with(['Torta', 'Bocadito']);

test('cabeceras duplicadas bloquean cada familia sin seleccionar una arbitraria', function (string $familia) {
    $payload = cantidadPayload($this->fixtures, $familia);
    $this->actingAs($this->fixtures['usuario'])->post(rutaCantidad($familia), $payload)->assertSessionHasNoErrors();
    Produccion::create(Produccion::sole()->only(['fecha', 'categoria_id', 'turno_id', 'registrado_por_usuario_id']));
    $this->post(rutaCantidad($familia), $payload)->assertSessionHasErrors('produccion');
    expect(Produccion::count())->toBe(2)->and(modeloCantidad($familia)::count())->toBe(1);
    $this->get(route('produccion.index', ['categoria' => strtolower($familia), 'fecha' => $payload['fecha'], 'turno_id' => $payload['turno_id']]))
        ->assertOk()->assertDontSee('data-detalle-registrado=', false);
})->with(['Torta', 'Bocadito']);

test('los tres registros conviven y la consulta solo carga la familia seleccionada', function () {
    $this->actingAs($this->fixtures['usuario']);
    $pan = cantidadPayload($this->fixtures, 'Pan');
    $pan['detalles'][0] += ['coches' => 1, 'latas_adicionales' => 2];
    $this->post(route('produccion.store'), $pan)->assertSessionHasNoErrors();
    foreach (['Torta', 'Bocadito'] as $familia) {
        $this->post(rutaCantidad($familia), cantidadPayload($this->fixtures, $familia))->assertSessionHasNoErrors();
    }
    expect(Produccion::count())->toBe(3)->and(DetallePan::sole()->cantidad)->toBe(20)
        ->and(DetalleTorta::sole()->cantidad)->toBe(4)->and(DetalleBocadito::sole()->cantidad)->toBe(4);
    foreach (['Pan', 'Torta', 'Bocadito'] as $familia) {
        $response = $this->get(route('produccion.index', ['categoria' => strtolower($familia), 'fecha' => $pan['fecha'], 'turno_id' => $pan['turno_id']]))
            ->assertOk()->assertSeeText('Nota de lote '.$familia);
        foreach (array_diff(['Pan', 'Torta', 'Bocadito'], [$familia]) as $otra) {
            $response->assertDontSeeText('Nota de lote '.$otra);
        }
        expect($response->viewData('detallesRegistrados'))->toHaveCount(1);
    }
});

test('Torta guarda imagen válida con nombre generado y enlace recuperable', function () {
    $payload = cantidadPayload($this->fixtures, 'Torta');
    $payload['detalles'][0]['foto'] = UploadedFile::fake()->image('nombre-cliente.png');
    $this->actingAs($this->fixtures['usuario'])->followingRedirects()->post(rutaCantidad('Torta'), $payload)
        ->assertOk()->assertSeeText('Ver foto')->assertSessionHasNoErrors();
    $ruta = DetalleTorta::sole()->foto;
    expect($ruta)->toStartWith('produccion/tortas/')->not->toContain('nombre-cliente');
    Storage::disk('public')->assertExists($ruta);
});

test('Torta rechaza fotos y formas inválidas', function (string $caso, string $campo) {
    $payload = cantidadPayload($this->fixtures, 'Torta');
    switch ($caso) {
        case 'texto': $payload['detalles'][0]['foto'] = UploadedFile::fake()->create('foto.txt', 1, 'text/plain');
            break;
        case 'ruta': $payload['detalles'][0]['foto'] = '../../foto.png';
            break;
        case 'SVG': $payload['detalles'][0]['foto'] = UploadedFile::fake()->createWithContent('foto.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
            break;
        case 'grande': $payload['detalles'][0]['foto'] = UploadedFile::fake()->image('foto.png')->size(2049);
            break;
        case 'sin forma': unset($payload['detalles'][0]['forma']);
            break;
        case 'forma larga': $payload['detalles'][0]['forma'] = str_repeat('a', 31);
            break;
        case 'forma array': $payload['detalles'][0]['forma'] = ['malformado'];
            break;
    }
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))->post(rutaCantidad('Torta'), $payload)
        ->assertRedirect()->assertSessionHasErrors($campo);
    $mensaje = session('errors')->first($campo);
    $this->withCookie(config('session.cookie'), app('session.store')->getId())
        ->get(route('produccion.index'))->assertOk()->assertSeeText($mensaje);
    expect(Produccion::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    ['texto', 'detalles.0.foto'], ['ruta', 'detalles.0.foto'], ['SVG', 'detalles.0.foto'], ['grande', 'detalles.0.foto'],
    ['sin forma', 'detalles.0.forma'], ['forma larga', 'detalles.0.forma'], ['forma array', 'detalles.0.forma'],
]);

test('fallo en segundo pivot revierte SQL y compensa foto conservando lotes previos', function (string $familia, bool $existente) {
    $payload = cantidadPayload($this->fixtures, $familia);
    $this->actingAs($this->fixtures['usuario']);
    if ($existente) {
        $this->post(rutaCantidad($familia), $payload)->assertSessionHasNoErrors();
    }
    if ($familia === 'Torta') {
        $payload['detalles'][0]['foto'] = UploadedFile::fake()->image('foto.png');
    }
    $dispatcher = Model::getEventDispatcher();
    Model::setEventDispatcher(clone $dispatcher);
    $intentos = 0;
    $pivotClass = $familia === 'Torta' ? DetalleTortaEmpleado::class : DetalleBocaditoEmpleado::class;
    try {
        $pivotClass::creating(function ($pivot) use (&$intentos) {
            if (++$intentos === 2) {
                $pivot->rol_produccion_id = RolProduccion::max('id') + 100;
            }
        });
        $this->post(rutaCantidad($familia), $payload)->assertSessionHasErrors('produccion');
        expect($intentos)->toBe(2)->and(Produccion::count())->toBe($existente ? 1 : 0)
            ->and(modeloCantidad($familia)::count())->toBe($existente ? 1 : 0)
            ->and(DB::table('detalle_'.strtolower($familia).'_empleado')->count())->toBe($existente ? 2 : 0)
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    } finally {
        Model::setEventDispatcher($dispatcher);
    }
})->with(['Torta', 'Bocadito'])->with([false, true]);

test('fallo al escribir la imagen impide guardar cabecera detalle y participantes', function () {
    $payload = cantidadPayload($this->fixtures, 'Torta');
    $payload['detalles'][0]['foto'] = UploadedFile::fake()->image('foto.png');
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturn(false);
    $disk->shouldReceive('delete')->once()->andReturn(true);
    Storage::shouldReceive('disk')->with('public')->andReturn($disk);
    $this->actingAs($this->fixtures['usuario'])->post(rutaCantidad('Torta'), $payload)->assertSessionHasErrors('produccion');
    expect(Produccion::count())->toBe(0)->and(DetalleTorta::count())->toBe(0)->and(DB::table('detalle_torta_empleado')->count())->toBe(0);
});

test('Action de cada familia revalida producto cantidad y participantes sin Request', function (string $familia, string $caso) {
    $payload = cantidadPayload($this->fixtures, $familia);
    if ($caso === 'cantidad') {
        $payload['detalles'][0]['cantidad'] = 0;
    } elseif ($caso === 'producto') {
        $payload['detalles'][0]['producto_id'] = $this->fixtures['productos']['Pan']->id;
    } else {
        $payload['detalles'][0]['participantes'] = [$payload['detalles'][0]['participantes'][0]];
    }
    $action = app($familia === 'Torta' ? RegistrarProduccionTorta::class : RegistrarProduccionBocadito::class);
    expect(fn () => $action->ejecutar($payload, $this->fixtures['usuario']->id))->toThrow(ValidationException::class)
        ->and(Produccion::count())->toBe(0);
})->with(['Torta', 'Bocadito'])->with(['cantidad', 'producto', 'participantes']);

test('invitados no pueden registrar las nuevas familias', function (string $familia) {
    $this->post(rutaCantidad($familia), cantidadPayload($this->fixtures, $familia))->assertRedirect(route('login'));
    expect(Produccion::count())->toBe(0);
})->with(['Torta', 'Bocadito']);

test('admite varios Ayudantes y observación opcional o texto cero', function (string $familia, ?string $observacion) {
    $payload = cantidadPayload($this->fixtures, $familia);
    $payload['detalles'][0]['observacion'] = $observacion;
    $payload['detalles'][0]['participantes'][] = [
        'empleado_id' => $this->fixtures['empleados'][2]->id,
        'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id,
    ];
    $this->actingAs($this->fixtures['usuario'])->post(rutaCantidad($familia), $payload)->assertSessionHasNoErrors();
    expect(modeloCantidad($familia)::sole()->observacion)->toBe($observacion)
        ->and(modeloCantidad($familia)::sole()->empleados)->toHaveCount(3);
})->with(['Torta', 'Bocadito'])->with([null, '0']);
