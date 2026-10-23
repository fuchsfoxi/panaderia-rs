<?php

use App\Models\Categoria;
use App\Models\DetallePan;
use App\Models\DetallePanEmpleado;
use App\Models\Empleado;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\RolProduccion;
use App\Models\Turno;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para probar el registro de Pan en MariaDB aislada.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();

    // Desplazar los catálogos sin IDs fijos detecta supuestos Pan/Maestro/Turno = 1.
    Categoria::create(['nombre_categorias' => 'Categoría previa ficticia']);
    RolProduccion::create(['nombre_roles_produccion' => 'Rol previo ficticio']);
    Turno::create(['nombre_turnos' => 'Turno previo ficticio']);
    // Estos escenarios de negocio registran o consultan cualquier turno.
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->fixtures['productos']['Pan']->update(['panes_por_lata' => 12]);
    $this->payload = [
        'categoria' => 'pan',
        'fecha' => '2026-10-05',
        'turno_id' => $this->fixtures['turno']->id,
        'detalles' => [[
            'producto_id' => $this->fixtures['productos']['Pan']->id,
            'hora_finalizacion' => '08:35',
            'coches' => 1,
            'latas_adicionales' => 5,
            'observacion' => 'Nota del primer lote',
            'participantes' => [
                ['empleado_id' => $this->fixtures['empleados'][0]->id, 'rol_produccion_id' => $this->fixtures['roles']['Maestro']->id],
                ['empleado_id' => $this->fixtures['empleados'][1]->id, 'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id],
            ],
        ]],
    ];
    $this->withoutVite();
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

function panContextUrl(array $payload): string
{
    return route('produccion.index', ['fecha' => $payload['fecha'], 'turno_id' => $payload['turno_id']]);
}

function assertNoPanStoreWrites(): void
{
    expect(Produccion::count())->toBe(0)
        ->and(DetallePan::count())->toBe(0)
        ->and(DB::table('detalle_pan_empleado')->count())->toBe(0);
}

test('un invitado no puede guardar producción Pan', function () {
    $this->post(route('produccion.store'), $this->payload)->assertRedirect(route('login'));
    $this->assertGuest();
    assertNoPanStoreWrites();
});

test('muestra el éxito después de guardar y seguir la redirección', function () {
    $this->actingAs($this->fixtures['usuario']);
    $response = $this->followingRedirects()->post(route('produccion.store'), $this->payload);
    $response->assertOk()->assertSeeText('Pan guardado')
        ->assertSee('role="status"', false)->assertDontSeeText('Revisa los campos indicados');
    expect(substr_count($response->getContent(), 'Pan guardado'))->toBe(1);
    $this->get(route('produccion.index'))->assertOk()->assertDontSeeText('Pan guardado');
});

test('muestra errores del producto y conserva campos y participantes al volver al formulario', function () {
    $this->payload['detalles'][0] = array_replace($this->payload['detalles'][0], [
        'coches' => 0, 'latas_adicionales' => 0, 'observacion' => 'Nota recuperada del producto',
        'participantes' => [$this->payload['detalles'][0]['participantes'][0]],
    ]);
    $this->actingAs($this->fixtures['usuario']);
    $response = $this->followingRedirects()->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload);
    $response->assertOk()->assertSeeText('Revisa los campos indicados')
        ->assertSeeText('Ingresa al menos un coche o una lata adicional.')
        ->assertSeeText('Cada detalle de Pan debe tener al menos un Ayudante.');

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//input[@name="fecha"]')->item(0)->getAttribute('value'))->toBe($this->payload['fecha'])
        ->and($xpath->query('//select[@name="turno_id"]/option[@selected]')->item(0)->getAttribute('value'))->toBe((string) $this->payload['turno_id']);
    foreach ($this->payload['detalles'] as $i => $detalle) {
        expect($xpath->query('//select[@name="detalles['.$i.'][producto_id]"]/option[@selected]')->item(0)->getAttribute('value'))->toBe((string) $detalle['producto_id'])
            ->and($xpath->query('//input[@name="detalles['.$i.'][coches]"]')->item(0)->getAttribute('value'))->toBe((string) $detalle['coches'])
            ->and($xpath->query('//input[@name="detalles['.$i.'][latas_adicionales]"]')->item(0)->getAttribute('value'))->toBe((string) $detalle['latas_adicionales'])
            ->and($xpath->query('//textarea[@name="detalles['.$i.'][observacion]"]')->item(0)->textContent)->toBe($detalle['observacion'])
            ->and($xpath->query('//input[@name="detalles[0][hora_finalizacion]"]')->item(0)->getAttribute('value'))->toBe('08:35')
            ->and($xpath->query('//select[@id="detalle-hora-finalizacion-hora"]/option[@selected]')->item(0)->getAttribute('value'))->toBe('08')
            ->and($xpath->query('//select[@id="detalle-hora-finalizacion-minutos"]/option[@selected]')->item(0)->getAttribute('value'))->toBe('35');
        foreach ($detalle['participantes'] as $j => $participante) {
            foreach ($participante as $campo => $id) {
                expect($xpath->query('//input[@name="detalles['.$i.'][participantes]['.$j.']['.$campo.']"]')->item(0)->getAttribute('value'))->toBe((string) $id);
            }
        }
    }
    expect($xpath->query('//fieldset[@data-indice="0"]//*[@role="alert" and contains(., "Ingresa al menos un coche")]')->length)->toBe(1)
        ->and($xpath->query('//fieldset[@data-indice="0"]//*[@role="alert" and contains(., "al menos un Ayudante")]')->length)->toBe(1)
        ->and($xpath->query('//fieldset[contains(@class, "detalle-pan")]')->length)->toBe(1);
    expect(substr_count($response->getContent(), 'Cada detalle de Pan debe tener al menos un Ayudante.'))->toBe(1);
    assertNoPanStoreWrites();
});

test('presenta errores estructurales junto a los campos relevantes', function (string $campo, mixed $valor, ?string $mensaje) {
    data_set($this->payload, $campo, $valor);
    $this->actingAs($this->fixtures['usuario']);
    $response = $this->followingRedirects()->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload);
    // Algunos mensajes numéricos usan la traducción por defecto del backend actual.
    $mensaje ??= $response->viewData('errors')->first($campo);
    expect($mensaje)->not->toBeEmpty();
    $response->assertOk()->assertSeeText('Revisa los campos indicados')->assertSeeText($mensaje);
    expect(substr_count($response->getContent(), $mensaje))->toBe(1);
    assertNoPanStoreWrites();
})->with([
    ['categoria', 'torta', 'Esta ruta solo permite registrar Pan.'],
    ['fecha', null, 'El campo fecha es obligatorio.'],
    ['turno_id', null, 'El campo turno es obligatorio.'],
    ['detalles', [], 'El campo detalles de Pan es obligatorio.'],
    ['detalles.0.producto_id', null, 'El campo producto es obligatorio.'],
    ['detalles.0.coches', -1, null],
    ['detalles.0.latas_adicionales', 18, null],
    ['detalles.0.participantes', [], 'El campo participantes es obligatorio.'],
    ['detalles.0.participantes.1.empleado_id', null, 'El campo empleado es obligatorio.'],
    ['detalles.0.participantes.1.rol_produccion_id', null, 'El campo rol de producción es obligatorio.'],
]);

test('guarda Pan con cabecera derivada cantidad snapshot y participantes reales', function () {
    $otroUsuario = Usuario::create([
        'username' => 'otro.usuario.ficticio',
        'password_hash' => $this->fixtures['usuario']->password_hash,
        'empleado_id' => $this->fixtures['empleados'][2]->id,
        'rol_id' => $this->fixtures['rol']->id,
    ]);
    $otraUnidad = UnidadMedida::create(['nombre_unidades_medida' => 'Otra unidad ficticia', 'equivalencia_unidades' => '1.00']);
    $otroTurno = Turno::create(['nombre_turnos' => 'Otro turno ficticio']);
    // Intentos de manipular campos derivados: ninguno puede determinar lo persistido.
    $this->payload['registrado_por_usuario_id'] = $otroUsuario->id;
    $this->payload['categoria_id'] = $this->fixtures['productos']['Torta']->categoria_id;
    $this->payload['detalles'][0] += [
        'total_latas' => 999, 'unidad_medida_id' => $otraUnidad->id,
        'panes_por_lata_usado' => 999, 'turno_id' => $otroTurno->id, 'produccion_id' => 999,
    ];

    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)
        ->assertRedirect(panContextUrl($this->payload))->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Pan guardado');

    $this->assertDatabaseCount('produccion', 1);
    $this->assertDatabaseCount('detalle_pan', 1);
    $this->assertDatabaseCount('detalle_pan_empleado', 2);
    $produccion = Produccion::with('detallesPan.empleados')->sole();
    $detalle = $produccion->detallesPan->sole();
    expect($produccion->fecha->toDateString())->toBe($this->payload['fecha'])
        ->and($produccion->registrado_por_usuario_id)->toBe($this->fixtures['usuario']->id)
        ->and($produccion->categoria_id)->toBe(Categoria::where('nombre_categorias', 'Pan')->sole()->id)
        ->and($produccion->turno_id)->toBe($this->fixtures['turno']->id)
        ->and($detalle->turno_id)->toBe($produccion->turno_id)
        ->and($detalle->producto_id)->toBe($this->fixtures['productos']['Pan']->id)
        ->and($detalle->cantidad)->toBe(23)
        ->and($detalle->hora_finalizacion)->toBe('08:35:00')
        ->and(array_key_exists('hora_finalizacion', $produccion->getAttributes()))->toBeFalse()
        ->and($detalle->unidad_medida_id)->toBe($this->fixtures['productos']['Pan']->unidad_medida_id)
        ->and($detalle->panes_por_lata_usado)->toBe(12)
        ->and($detalle->observacion)->toBe('Nota del primer lote');
    foreach ($this->payload['detalles'][0]['participantes'] as $participante) {
        $this->assertDatabaseHas('detalle_pan_empleado', ['detalle_pan_id' => $detalle->id] + $participante);
    }
    expect($detalle->empleados->where('pivot.rol_produccion_id', $this->fixtures['roles']['Maestro']->id))->toHaveCount(1);
});

test('Pan LATA rechaza factor NULL y conserva observación opcional con factor válido', function () {
    $producto = $this->fixtures['productos']['Pan'];
    $producto->update(['panes_por_lata' => null]);
    unset($this->payload['detalles'][0]['observacion']);
    $this->actingAs($this->fixtures['usuario'])->postJson(route('produccion.store'), $this->payload)
        ->assertUnprocessable()->assertJsonValidationErrors('detalles.0.producto_id');
    assertNoPanStoreWrites();
    $producto->update(['panes_por_lata' => 12]);
    $this->post(route('produccion.store'), $this->payload)->assertRedirect(panContextUrl($this->payload))->assertSessionHasNoErrors();
    expect(DetallePan::sole()->panes_por_lata_usado)->toBe(12)
        ->and(DetallePan::sole()->observacion)->toBeNull();
    $this->get(panContextUrl($this->payload))->assertOk()->assertDontSee('tarjeta-observacion', false);
});

test('reutiliza la cabecera en peticiones sucesivas con productos y observaciones independientes', function () {
    $otroPan = Producto::create([
        'nombre_p' => 'Segundo Pan ficticio', 'categoria_id' => $this->fixtures['productos']['Pan']->categoria_id,
        'unidad_medida_id' => $this->fixtures['unidad']->id, 'panes_por_lata' => 14,
    ]);
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)
        ->assertRedirect(panContextUrl($this->payload))->assertSessionHasNoErrors();
    $this->payload['detalles'][0] = array_replace($this->payload['detalles'][0], [
        'producto_id' => $otroPan->id, 'coches' => 0, 'latas_adicionales' => 7,
        'observacion' => 'Nota independiente del segundo lote', 'hora_finalizacion' => '09:20',
    ]);
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)
        ->assertRedirect(panContextUrl($this->payload))->assertSessionHasNoErrors();
    $this->assertDatabaseCount('produccion', 1);
    $this->assertDatabaseCount('detalle_pan', 2);
    $this->assertDatabaseCount('detalle_pan_empleado', 4);
    $detalles = Produccion::sole()->detallesPan()->orderBy('id')->get();
    expect($detalles->pluck('cantidad')->all())->toBe([23, 7])
        ->and($detalles->pluck('hora_finalizacion')->all())->toBe(['08:35:00', '09:20:00'])
        ->and($detalles->pluck('panes_por_lata_usado')->all())->toBe([12, 14])
        ->and($detalles->pluck('observacion')->all())->toBe(['Nota del primer lote', 'Nota independiente del segundo lote']);
    foreach ($detalles as $detalle) {
        expect($detalle->turno_id)->toBe($this->fixtures['turno']->id)
            ->and($detalle->empleados->modelKeys())->toEqualCanonicalizing($this->fixtures['empleados']->take(2)->pluck('id')->all());
    }
});

test('acepta varios Ayudantes sin depender del cargo del empleado', function () {
    // El cargo no decide el rol actual; la participación conserva su propia copia.
    $this->payload['detalles'][0]['participantes'][] = [
        'empleado_id' => $this->fixtures['empleados'][2]->id,
        'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id,
    ];
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)
        ->assertRedirect(panContextUrl($this->payload))->assertSessionHasNoErrors();
    $empleados = DetallePan::sole()->empleados;
    expect($empleados)->toHaveCount(3)
        ->and($empleados->where('pivot.rol_produccion_id', $this->fixtures['roles']['Maestro']->id))->toHaveCount(1)
        ->and($empleados->where('pivot.rol_produccion_id', $this->fixtures['roles']['Ayudante']->id))->toHaveCount(2);
});

test('rechaza composición inválida de participantes sin dejar registros', function (string $caso, string $campo) {
    $participantes = &$this->payload['detalles'][0]['participantes'];
    switch ($caso) {
        case 'sin Maestro':
            $participantes[0]['rol_produccion_id'] = $this->fixtures['roles']['Ayudante']->id;
            break;
        case 'dos Maestros':
            $participantes[1]['rol_produccion_id'] = $this->fixtures['roles']['Maestro']->id;
            break;
        case 'sin Ayudantes':
            $participantes = [$participantes[0]];
            break;
        case 'empleado duplicado':
            $participantes[1]['empleado_id'] = (string) $participantes[0]['empleado_id'];
            break;
        case 'otro rol':
            $participantes[1]['rol_produccion_id'] = $this->fixtures['roles']['Practicante']->id;
            break;
        case 'empleado inexistente':
            $participantes[1]['empleado_id'] = $this->fixtures['empleados']->max('id') + 100;
            break;
        case 'rol inexistente':
            $participantes[1]['rol_produccion_id'] = RolProduccion::max('id') + 100;
            break;
    }
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload)->assertRedirect(route('produccion.index'))
        ->assertSessionHasErrors($campo)->assertSessionHasInput('detalles', $this->payload['detalles']);
    assertNoPanStoreWrites();
})->with([
    ['sin Maestro', 'detalles.0.participantes'],
    ['dos Maestros', 'detalles.0.participantes'],
    ['sin Ayudantes', 'detalles.0.participantes'],
    ['empleado duplicado', 'detalles.0.participantes'],
    ['otro rol', 'detalles.0.participantes.1.rol_produccion_id'],
    ['empleado inexistente', 'detalles.0.participantes.1.empleado_id'],
    ['rol inexistente', 'detalles.0.participantes.1.rol_produccion_id'],
]);

test('rechaza productos manipulados fuera de Pan o inactivos', function (string $caso) {
    if ($caso === 'inactivo') {
        $this->fixtures['productos']['Pan']->update(['activo' => false]);
    } else {
        $this->payload['detalles'][0]['producto_id'] = $this->fixtures['productos'][$caso]->id;
    }
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload)->assertRedirect(route('produccion.index'))
        ->assertSessionHasErrors('detalles.0.producto_id')->assertSessionHasInput('detalles', $this->payload['detalles']);
    assertNoPanStoreWrites();
})->with(['Torta', 'Bocadito', 'inactivo']);

test('rechaza entradas inválidas y conserva el formulario sin escrituras', function (string $campo, mixed $valor) {
    data_set($this->payload, $campo, $valor);
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload)->assertRedirect(route('produccion.index'))
        ->assertSessionHasErrors($campo)->assertSessionHas('_old_input', fn ($input) => array_key_exists('categoria', $input) && $input['categoria'] === $this->payload['categoria']);
    assertNoPanStoreWrites();
})->with([
    ['categoria', 'Pan'], ['categoria', 'otra'], ['categoria', null],
    ['fecha', null], ['fecha', '2026-02-30'],
    ['turno_id', null], ['turno_id', 'no-es-id'],
    ['detalles', []], ['detalles', 'incorrecto'], ['detalles.0', 'incorrecto'],
    ['detalles.0.producto_id', null],
    ['detalles.0.coches', 'abc'], ['detalles.0.coches', -1], ['detalles.0.coches', 1.5],
    ['detalles.0.coches', 119304648],
    ['detalles.0.latas_adicionales', 'abc'], ['detalles.0.latas_adicionales', -1],
    ['detalles.0.latas_adicionales', 18], ['detalles.0.latas_adicionales', 2.5],
    ['detalles.0.observacion', ['incorrecto']],
    ['detalles.0.participantes', []], ['detalles.0.participantes', 'incorrecto'],
    ['detalles.0.participantes.0', 'incorrecto'],
    ['detalles.0.participantes.0.empleado_id', null],
    ['detalles.0.participantes.0.rol_produccion_id', null],
]);

test('rechaza IDs inexistentes de turno y producto', function (string $campo, string $modelo) {
    data_set($this->payload, $campo, $modelo::max('id') + 100);
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload)->assertRedirect(route('produccion.index'))
        ->assertSessionHasErrors($campo);
    assertNoPanStoreWrites();
})->with([['turno_id', Turno::class], ['detalles.0.producto_id', Producto::class]]);

test('rechaza total cero y total que excede la columna cantidad', function (int $coches, int $latas) {
    $this->payload['detalles'][0]['coches'] = $coches;
    $this->payload['detalles'][0]['latas_adicionales'] = $latas;
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload)->assertRedirect(route('produccion.index'))
        ->assertSessionHasErrors('detalles.0.coches');
    assertNoPanStoreWrites();
})->with([[0, 0], [119304647, 17]]);

test('rechaza Torta y Bocadito con un mensaje comprensible', function (string $familia) {
    $this->payload['categoria'] = $familia;
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload)->assertRedirect(route('produccion.index'))
        ->assertSessionHasErrors(['categoria' => 'Esta ruta solo permite registrar Pan.'])
        ->assertSessionHasInput('categoria', $familia);
    assertNoPanStoreWrites();
})->with(['torta', 'bocadito']);

test('rechaza producto sin unidad válida antes de crear la cabecera', function () {
    // Solo en esta instancia desechable: simular un catálogo legacy corrupto.
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    try {
        DB::table('productos')->where('id', $this->fixtures['productos']['Pan']->id)
            ->update(['unidad_medida_id' => UnidadMedida::max('id') + 100]);
    } finally {
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload)->assertRedirect(route('produccion.index'))
        ->assertSessionHasErrors('detalles.0.producto_id');
    assertNoPanStoreWrites();
});

test('rechaza dos detalles en un mismo POST sin registrar ninguno', function () {
    $this->payload['detalles'][] = $this->payload['detalles'][0];
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload)->assertRedirect(route('produccion.index'))
        ->assertSessionHasErrors(['detalles' => 'Registre exactamente un producto de Pan por envío.'])
        ->assertSessionHasInput('detalles', $this->payload['detalles']);
    assertNoPanStoreWrites();
});

test('rollback revierte solo esta petición ante un fallo real de detalle o pivot', function (string $modelo, int $fallarEn, bool $sesionExistente) {
    $this->actingAs($this->fixtures['usuario']);
    if ($sesionExistente) {
        $this->post(route('produccion.store'), $this->payload)->assertSessionHasNoErrors();
    }
    $cabecerasAntes = Produccion::get()->toArray();
    $detallesAntes = DetallePan::get()->toArray();
    $pivotsAntes = DB::table('detalle_pan_empleado')->orderBy('detalle_pan_id')->orderBy('empleado_id')->get()->toArray();
    $originalDispatcher = Model::getEventDispatcher();
    Model::setEventDispatcher(clone $originalDispatcher);
    $intentos = 0;
    $nivelInicial = DB::transactionLevel();
    $rolInexistente = RolProduccion::max('id') + 100;
    try {
        $modelo::creating(function ($registro) use (&$intentos, $fallarEn, $rolInexistente, $sesionExistente) {
            if (++$intentos === $fallarEn) {
                // La cabecera existe; el fallo del segundo pivot ocurre tras insertar detalle y primer pivot.
                expect(Produccion::count())->toBe(1)
                    ->and(DetallePan::count())->toBe((int) $sesionExistente + ($registro instanceof DetallePan ? 0 : 1))
                    ->and(DB::table('detalle_pan_empleado')->count())->toBe(2 * (int) $sesionExistente + ($registro instanceof DetallePan ? 0 : 1));
                if ($registro instanceof DetallePan) {
                    $registro->cantidad = 0; // CHECK real de MariaDB.
                } else {
                    $registro->rol_produccion_id = $rolInexistente; // FK real de MariaDB.
                }
            }
        });
        $this->from(route('produccion.index'))->post(route('produccion.store'), $this->payload)->assertRedirect(route('produccion.index'))
            ->assertSessionHasErrors(['produccion' => 'No se pudo guardar la producción de Pan. No se registró ningún dato; vuelva a intentarlo.'])
            ->assertSessionHasInput('detalles', $this->payload['detalles']);
        $feedback = $this->withCookie(config('session.cookie'), app('session.store')->getId())
            ->get(route('produccion.index'))->assertOk();
        expect($feedback->viewData('errors')->getMessages())->toHaveKey('produccion');
        $feedback->assertSeeText('No se pudo guardar la producción de Pan. No se registró ningún dato; vuelva a intentarlo.')
            ->assertDontSeeText('SQLSTATE');
        expect($intentos)->toBe($fallarEn)->and(DB::transactionLevel())->toBe($nivelInicial)
            ->and(Produccion::get()->toArray())->toBe($cabecerasAntes)
            ->and(DetallePan::get()->toArray())->toBe($detallesAntes)
            ->and(DB::table('detalle_pan_empleado')->orderBy('detalle_pan_id')->orderBy('empleado_id')->get()->toArray())->toEqual($pivotsAntes);
    } finally {
        Model::setEventDispatcher($originalDispatcher);
    }
})->with([
    'detalle inicial' => [DetallePan::class, 1, false],
    'pivot inicial después de insertar un pivot' => [DetallePanEmpleado::class, 2, false],
    'detalle en sesión existente' => [DetallePan::class, 1, true],
    'pivot en sesión existente' => [DetallePanEmpleado::class, 2, true],
]);

test('registra cantidades por coches y latas sin depender del cálculo del navegador', function (mixed $coches, mixed $latas) {
    $this->travelTo(now()->startOfDay());
    $this->payload['fecha'] = now()->toDateString();
    $this->payload['detalles'][0]['coches'] = $coches;
    $this->payload['detalles'][0]['latas_adicionales'] = $latas;
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)
        ->assertRedirect(panContextUrl($this->payload))->assertSessionHasNoErrors();
    $produccion = Produccion::sole();
    $detalle = DetallePan::sole();
    expect($detalle->cantidad)->toBe((int) $coches * DetallePan::LATAS_POR_COCHE + (int) $latas)
        ->and($detalle->producto_id)->toBe($this->fixtures['productos']['Pan']->id)
        ->and($detalle->turno_id)->toBe($this->payload['turno_id'])
        ->and($detalle->produccion_id)->toBe($produccion->id)
        ->and($produccion->turno_id)->toBe($this->payload['turno_id']);
    foreach ($this->payload['detalles'][0]['participantes'] as $participante) {
        $this->assertDatabaseHas('detalle_pan_empleado', ['detalle_pan_id' => $detalle->id] + $participante);
    }
    $dashboard = $this->get(route('dashboard'))->assertOk();
    expect($dashboard->viewData('totalLotesHoy'))->toBe(1)
        ->and($dashboard->viewData('totalLatasHoy'))->toBe($detalle->cantidad);
    $historial = $this->get(route('history.index'))->assertOk()
        ->assertSeeText('Cantidad: '.$detalle->cantidad.' latas');
    expect($historial->viewData('detalles')->sole()->id)->toBe($detalle->id);
})->with([
    [0, 1], [0, 9], [1, 0], [1, 5], [2, 3],
    'solo latas' => [0, 12], 'solo coches' => [2, 0], 'ambos positivos' => [2, 5],
    'coches vacío' => ['', 12], 'latas vacío' => [2, ''],
    'coches null' => [null, 12], 'latas null' => [2, null],
]);

test('normaliza cantidades vacías a cero y rechaza el total cero sin escrituras', function (mixed $coches, mixed $latas) {
    $this->payload['detalles'][0]['coches'] = $coches;
    $this->payload['detalles'][0]['latas_adicionales'] = $latas;
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload)->assertRedirect(route('produccion.index'))
        ->assertSessionHasErrors(['detalles.0.coches' => 'Ingresa al menos un coche o una lata adicional.'])
        ->assertSessionHasInput('detalles.0.coches', 0)
        ->assertSessionHasInput('detalles.0.latas_adicionales', 0);
    assertNoPanStoreWrites();
})->with(['ceros' => [0, 0], 'ambos vacíos' => ['', ''], 'ambos null' => [null, null]]);

test('un campo de cantidad omitido se normaliza sin fabricar estructuras de detalles', function (string $campo) {
    unset($this->payload['detalles'][0][$campo]);
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)
        ->assertRedirect(panContextUrl($this->payload))->assertSessionHasNoErrors();
    expect(DetallePan::sole()->cantidad)->toBe($campo === 'coches' ? 5 : DetallePan::LATAS_POR_COCHE);
})->with(['coches', 'latas_adicionales']);

test('repetir el producto crea detalles separados sin sumar ni alterar snapshots anteriores', function () {
    $this->payload['detalles'][0]['coches'] = 0;
    $this->payload['detalles'][0]['latas_adicionales'] = 9;
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)->assertSessionHasNoErrors();
    $primero = DetallePan::sole();
    $this->fixtures['empleados'][2]->update(['rol_produccion_id' => $this->fixtures['roles']['Maestro']->id]);
    $this->fixtures['empleados'][0]->update(['rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id]);
    $this->fixtures['productos']['Pan']->update(['panes_por_lata' => 14]);
    $otroUsuario = Usuario::create([
        'username' => 'segundo.registrador.ficticio', 'password_hash' => $this->fixtures['usuario']->password_hash,
        'empleado_id' => $this->fixtures['empleados'][2]->id, 'rol_id' => $this->fixtures['rol']->id,
    ]);
    $this->payload['detalles'][0] = array_replace($this->payload['detalles'][0], [
        'coches' => 1, 'latas_adicionales' => 0, 'observacion' => 'Otro lote del mismo producto',
        'participantes' => [
            ['empleado_id' => $this->fixtures['empleados'][2]->id, 'rol_produccion_id' => $this->fixtures['roles']['Maestro']->id],
            ['empleado_id' => $this->fixtures['empleados'][0]->id, 'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id],
        ],
    ]);
    $this->actingAs($otroUsuario)->post(route('produccion.store'), $this->payload)
        ->assertRedirect(panContextUrl($this->payload))->assertSessionHasNoErrors();
    $detalles = DetallePan::with('empleados')->orderBy('id')->get();
    expect(Produccion::count())->toBe(1)
        ->and(Produccion::sole()->registrado_por_usuario_id)->toBe($this->fixtures['usuario']->id)
        ->and($detalles)->toHaveCount(2)
        ->and($detalles->pluck('producto_id')->all())->toBe([$primero->producto_id, $primero->producto_id])
        ->and($detalles->pluck('cantidad')->all())->toBe([9, 18])
        ->and($detalles->pluck('panes_por_lata_usado')->all())->toBe([12, 14])
        ->and($detalles->pluck('observacion')->all())->toBe(['Nota del primer lote', 'Otro lote del mismo producto'])
        ->and($detalles[0]->empleados->modelKeys())->toEqualCanonicalizing($this->fixtures['empleados']->take(2)->pluck('id')->all())
        ->and($detalles[1]->empleados->modelKeys())->toEqualCanonicalizing([$this->fixtures['empleados'][2]->id, $this->fixtures['empleados'][0]->id]);
    foreach ($this->payload['detalles'][0]['participantes'] as $participante) {
        $this->assertDatabaseHas('detalle_pan_empleado', ['detalle_pan_id' => $detalles[1]->id] + $participante);
    }
});

test('otra fecha u otro turno crea otra sesión de Pan', function (string $contexto) {
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)->assertSessionHasNoErrors();
    if ($contexto === 'fecha') {
        $this->payload['fecha'] = '2026-10-06';
    } else {
        $this->payload['turno_id'] = Turno::create(['nombre_turnos' => 'Noche'])->id;
    }
    $this->post(route('produccion.store'), $this->payload)
        ->assertRedirect(panContextUrl($this->payload))->assertSessionHasNoErrors();
    expect(Produccion::count())->toBe(2)->and(DetallePan::count())->toBe(2)
        ->and(DetallePan::get()->pluck('produccion_id')->unique())->toHaveCount(2);
    $ultima = Produccion::orderByDesc('id')->first();
    expect($ultima->fecha->toDateString())->toBe($this->payload['fecha'])
        ->and($ultima->turno_id)->toBe($this->payload['turno_id']);
})->with(['fecha', 'turno']);

test('rechaza cabeceras duplicadas sin elegir una ni alterar los registros existentes', function () {
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)->assertSessionHasNoErrors();
    $original = Produccion::sole();
    Produccion::create($original->only(['fecha', 'categoria_id', 'turno_id', 'registrado_por_usuario_id']));
    $detalleOriginal = DetallePan::sole()->toArray();
    $this->from(panContextUrl($this->payload))->post(route('produccion.store'), $this->payload)
        ->assertRedirect(panContextUrl($this->payload))->assertSessionHasErrors('produccion')
        ->assertSessionHasInput('detalles', $this->payload['detalles']);
    expect(Produccion::count())->toBe(2)->and(DetallePan::sole()->toArray())->toBe($detalleOriginal)
        ->and(DB::table('detalle_pan_empleado')->count())->toBe(2);
    $response = $this->withCookie(config('session.cookie'), app('session.store')->getId())
        ->get(panContextUrl($this->payload))->assertOk()
        ->assertSeeText('Existen varias sesiones de Pan para esta fecha y turno.')
        ->assertDontSee('data-detalle-registrado=', false);
    expect(substr_count($response->getContent(), 'Existen varias sesiones de Pan para esta fecha y turno.'))->toBe(1);
});

test('éxito conserva fecha y turno y deja un único formulario de producto limpio', function () {
    $this->actingAs($this->fixtures['usuario']);
    $response = $this->followingRedirects()->post(route('produccion.store'), $this->payload)->assertOk()
        ->assertSeeText('Guardar')->assertDontSeeText('Agregar producto')
        ->assertDontSeeText('Eliminar producto')->assertSeeText('Latas adicionales')
        ->assertSeeText('Cancelar')
        ->assertSee('data-resumen-vacio', false)->assertDontSee('plantilla-detalle-pan', false);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//input[@name="fecha"]')->item(0)->getAttribute('value'))->toBe($this->payload['fecha'])
        ->and($xpath->query('//select[@name="turno_id"]/option[@selected]')->item(0)->getAttribute('value'))->toBe((string) $this->payload['turno_id'])
        ->and($xpath->query('//fieldset[contains(@class, "detalle-pan")]')->length)->toBe(1)
        ->and($xpath->query('//select[@name="detalles[0][producto_id]"]/option[@selected]')->length)->toBe(0)
        ->and($xpath->query('//input[@name="detalles[0][coches]"]')->item(0)->getAttribute('value'))->toBe('0')
        ->and($xpath->query('//input[@name="detalles[0][latas_adicionales]"]')->item(0)->getAttribute('value'))->toBe('0')
        ->and($xpath->query('//textarea[@name="detalles[0][observacion]"]')->item(0)->textContent)->toBe('')
        ->and($xpath->query('//fieldset//input[@data-participante-campo]')->length)->toBe(0)
        ->and($xpath->query('//article[@data-detalle-registrado]')->length)->toBe(1);

    foreach (['torta' => 'Torta', 'bocadito' => 'Bocadito'] as $familia => $nombre) {
        $boton = $xpath->query('//button[@data-categoria="'.$familia.'"]')->item(0);
        expect($boton->hasAttribute('disabled'))->toBeFalse()
            ->and(trim($boton->textContent))->toBe($nombre);
    }
    expect($xpath->query('//button[@data-categoria="pan"]')->item(0)->getAttribute('aria-pressed'))->toBe('true');
    foreach (['coches', 'latas_adicionales'] as $campo) {
        $input = $xpath->query('//input[@name="detalles[0]['.$campo.']"]')->item(0);
        expect($input->getAttribute('min'))->toBe('0')
            ->and($input->getAttribute('step'))->toBe('1')
            ->and($input->hasAttribute('required'))->toBeFalse();
    }
    expect($xpath->query('//form[@id="formulario-produccion"]')->item(0)->getAttribute('data-latas-por-coche'))
        ->toBe((string) DetallePan::LATAS_POR_COCHE);
    $response->assertDontSeeText('Ingresa al menos un coche o una lata adicional.');
});

test('producción explica los datos faltantes sin impedir el renderizado del formulario', function (string $catalogo, string $mensaje) {
    // Cambios exclusivamente en fixtures dentro de la transacción aislada.
    if ($catalogo === 'roles') {
        Empleado::query()->update(['rol_produccion_id' => null]);
    }
    match ($catalogo) {
        'productos' => Producto::query()->update(['activo' => false]),
        'turnos' => Turno::query()->delete(),
        'roles' => RolProduccion::where('nombre_roles_produccion', 'Ayudante')->delete(),
    };
    $this->actingAs($this->fixtures['usuario'])->get(route('produccion.index'))->assertOk()
        ->assertSeeText('Faltan datos para registrar la producción.')
        ->assertSeeText($mensaje)
        ->assertSee('id="formulario-produccion"', false);
    assertNoPanStoreWrites();
})->with([
    ['productos', 'No hay productos de Pan disponibles.'],
    ['turnos', 'No hay turnos disponibles.'],
    ['roles', 'Faltan los roles Maestro o Ayudante.'],
]);

test('index muestra tarjetas independientes ordenadas y filtra por fecha turno y categoría Pan', function () {
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)->assertSessionHasNoErrors();
    $this->payload['detalles'][0]['coches'] = 2;
    $this->payload['detalles'][0]['latas_adicionales'] = 3;
    $this->payload['detalles'][0]['observacion'] = 'Nota de otra tarjeta del mismo producto';
    $this->post(route('produccion.store'), $this->payload)->assertSessionHasNoErrors();
    $esperados = DetallePan::orderByDesc('id')->pluck('id')->all();
    $otraFecha = array_replace($this->payload, ['fecha' => '2026-10-06']);
    $otraFecha['detalles'][0]['observacion'] = 'Ocultar otra fecha';
    $this->post(route('produccion.store'), $otraFecha)->assertSessionHasNoErrors();
    $otroTurno = array_replace($this->payload, ['turno_id' => Turno::create(['nombre_turnos' => 'Noche'])->id]);
    $otroTurno['detalles'][0]['observacion'] = 'Ocultar otro turno';
    $this->post(route('produccion.store'), $otroTurno)->assertSessionHasNoErrors();
    // Registro legacy artificial: comprueba el filtro de categoría sin implementar otra familia.
    $otraCategoria = Produccion::create([
        'fecha' => $this->payload['fecha'], 'turno_id' => $this->payload['turno_id'],
        'categoria_id' => $this->fixtures['productos']['Torta']->categoria_id,
        'registrado_por_usuario_id' => $this->fixtures['usuario']->id,
    ]);
    $otraCategoria->detallesPan()->create([
        'producto_id' => $this->fixtures['productos']['Pan']->id, 'cantidad' => 1,
        'unidad_medida_id' => $this->fixtures['unidad']->id, 'turno_id' => $this->payload['turno_id'],
        'observacion' => 'Ocultar otra categoría',
    ]);
    $response = $this->get(panContextUrl($this->payload))->assertOk()
        ->assertSeeText('Producción registrada')->assertSeeText('Turno: '.$this->fixtures['turno']->nombre_turnos)
        ->assertSeeText('1 coches + 5 latas adicionales')
        ->assertSeeText('2 coches + 3 latas adicionales')
        ->assertSeeText('Nota del primer lote')->assertSeeText('Nota de otra tarjeta del mismo producto')
        ->assertDontSeeText('Ocultar otra fecha')->assertDontSeeText('Ocultar otro turno')->assertDontSeeText('Ocultar otra categoría');
    expect($response->viewData('detallesRegistrados')->modelKeys())->toBe($esperados);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $tarjetas = $xpath->query('//article[@data-detalle-registrado]');
    expect($tarjetas->length)->toBe(2);
    foreach ($tarjetas as $i => $tarjeta) {
        expect($tarjeta->getAttribute('data-detalle-registrado'))->toBe((string) $esperados[$i])
            ->and($xpath->query('.//h3', $tarjeta)->item(0)->textContent)->toBe($this->fixtures['productos']['Pan']->nombre_p);
        foreach (['Maestro' => 0, 'Ayudante' => 1] as $rol => $empleado) {
            expect($tarjeta->textContent)->toContain(($rol === 'Ayudante' ? 'Ayudantes' : $rol).': '.$this->fixtures['empleados'][$empleado]->nombre_empleados);
        }
    }
});

test('index sin turno consulta todos los turnos y rechaza contextos inválidos', function (string $caso) {
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)->assertSessionHasNoErrors();
    $consulta = match ($caso) {
        'sin turno' => ['fecha' => $this->payload['fecha']],
        'fecha inválida' => ['fecha' => '2026-02-30', 'turno_id' => $this->payload['turno_id']],
        'turno inexistente' => ['fecha' => $this->payload['fecha'], 'turno_id' => Turno::max('id') + 100],
        'fecha como array' => ['fecha' => ['2026-10-05'], 'turno_id' => $this->payload['turno_id']],
        'turno como array' => ['fecha' => $this->payload['fecha'], 'turno_id' => [$this->payload['turno_id']]],
    };
    $response = $this->get(route('produccion.index', $consulta))->assertOk();
    if ($caso === 'sin turno') {
        $response->assertSee('data-detalle-registrado=', false);
        expect($response->viewData('detalles')->total())->toBe(1);
    } else {
        $response->assertDontSee('data-detalle-registrado=', false)->assertSeeText('La fecha o el turno de consulta no son válidos.');
    }
})->with(['sin turno', 'fecha inválida', 'turno inexistente', 'fecha como array', 'turno como array']);

test('rechaza un único detalle enviado con un índice distinto de cero', function () {
    $this->payload['detalles'] = [1 => $this->payload['detalles'][0]];
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload)->assertRedirect(route('produccion.index'))
        ->assertSessionHasErrors(['detalles' => 'El producto de Pan debe enviarse en detalles[0].']);
    assertNoPanStoreWrites();
});

test('una tarjeta conserva la observación de texto cero', function () {
    $this->payload['detalles'][0]['observacion'] = '0';
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)->assertSessionHasNoErrors();
    $this->get(panContextUrl($this->payload))->assertOk()->assertSee('class="observacion">0</p>', false);
});

test('recupera el formulario después de rechazar old input malformado sin HTTP 500', function (string $campo, mixed $valor) {
    data_set($this->payload, $campo, $valor);
    $this->actingAs($this->fixtures['usuario']);
    $this->from(route('produccion.index'))->post(route('produccion.store'), $this->payload)
        ->assertRedirect(route('produccion.index'))->assertSessionHasErrors($campo);
    $mensaje = session('errors')->first($campo);
    expect($mensaje)->not->toBeEmpty();

    $formulario = $this->withCookie(config('session.cookie'), app('session.store')->getId())
        ->get(route('produccion.index'))->assertOk()
        ->assertSeeText('Revisa los campos indicados')->assertSeeText($mensaje)
        ->assertDontSeeText('contenido-malformado')->assertDontSeeText('SQLSTATE');

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$formulario->getContent());
    $xpath = new DOMXPath($document);
    if (in_array($campo, ['detalles.0.coches', 'detalles.0.latas_adicionales'], true)) {
        $nombre = str_replace('detalles.0.', '', $campo);
        expect($xpath->query('//input[@name="detalles[0]['.$nombre.']"]')->item(0)->getAttribute('value'))->toBe('');
    }
    if ($campo === 'detalles.0.observacion') {
        expect($xpath->query('//textarea[@name="detalles[0][observacion]"]')->item(0)->textContent)->toBe('');
    }
    // Los campos válidos no se pierden por sanear el campo rechazado.
    if (str_starts_with($campo, 'detalles.')) {
        expect($xpath->query('//input[@name="fecha"]')->item(0)->getAttribute('value'))->toBe($this->payload['fecha']);
    }
    assertNoPanStoreWrites();
})->with([
    ['detalles.0.producto_id', ['contenido-malformado']],
    ['detalles.0.coches', ['contenido-malformado']],
    ['detalles.0.latas_adicionales', ['contenido-malformado']],
    ['detalles.0.observacion', ['contenido-malformado']],
    ['detalles', 'contenido-malformado'],
    ['detalles.0', 'contenido-malformado'],
    ['detalles.0.participantes', 'contenido-malformado'],
    ['detalles.0.participantes.0', 'contenido-malformado'],
    ['detalles.0.participantes.0.empleado_id', ['contenido-malformado']],
    ['detalles.0.participantes.0.rol_produccion_id', ['contenido-malformado']],
    ['fecha', ['contenido-malformado']],
    ['turno_id', ['contenido-malformado']],
]);

test('rechaza fechas POST fuera del contrato ISO sin escrituras', function (mixed $fecha) {
    $this->payload['fecha'] = $fecha;
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload)->assertRedirect(route('produccion.index'))
        ->assertSessionHasErrors('fecha');
    assertNoPanStoreWrites();
})->with(['10/05/2026', '2026-10-05 12:00:00', '2026-1-5', [['2026-10-05']]]);

test('rechaza observaciones que exceden TEXT antes de escribir con error de campo', function (string $observacion) {
    $this->payload['detalles'][0]['observacion'] = $observacion;
    $this->actingAs($this->fixtures['usuario']);
    $this->from(route('produccion.index'))->post(route('produccion.store'), $this->payload)
        ->assertRedirect(route('produccion.index'))->assertSessionHasErrors('detalles.0.observacion');
    $this->withCookie(config('session.cookie'), app('session.store')->getId())
        ->get(route('produccion.index'))->assertOk()
        ->assertSeeText('La observación no puede superar 65535 bytes.')
        ->assertDontSeeText('No se pudo guardar la producción de Pan.');
    assertNoPanStoreWrites();
})->with([
    'ASCII' => fn () => str_repeat('a', 65536),
    'Unicode de dos bytes' => fn () => str_repeat('á', 32768),
    'Unicode de cuatro bytes' => fn () => str_repeat('🍞', 16384),
]);

test('conserva observaciones dentro de la capacidad TEXT sin truncarlas', function (string $observacion) {
    $this->payload['detalles'][0]['observacion'] = $observacion;
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)
        ->assertRedirect(panContextUrl($this->payload))->assertSessionHasNoErrors();
    expect(DetallePan::sole()->observacion)->toBe($observacion);
})->with([
    'ASCII máximo' => fn () => str_repeat('a', 65535),
    'Unicode máximo en bytes' => fn () => str_repeat('á', 32767).'a',
]);

test('old input conserva el contexto POST sobre los filtros GET al recuperar errores', function () {
    $this->payload['detalles'][0]['coches'] = 0;
    $this->payload['detalles'][0]['latas_adicionales'] = 0;
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index'))
        ->post(route('produccion.store'), $this->payload)->assertSessionHasErrors('detalles.0.coches');
    $response = $this->withCookie(config('session.cookie'), app('session.store')->getId())
        ->get(route('produccion.index', ['fecha' => '2026-02-30', 'turno_id' => ['incorrecto']]))
        ->assertOk()->assertSeeText('Ingresa al menos un coche o una lata adicional.');
    expect($response->viewData('fechaSeleccionada'))->toBe($this->payload['fecha'])
        ->and($response->viewData('turnoSeleccionadoId'))->toBe((string) $this->payload['turno_id'])
        ->and($response->viewData('errorConsulta'))->toBeNull();
    assertNoPanStoreWrites();
});

test('Pan rechaza una hora faltante o inválida sin escrituras y conserva su familia', function (mixed $hora) {
    $this->payload['detalles'][0]['hora_finalizacion'] = $hora;
    $this->actingAs($this->fixtures['usuario'])->from(route('produccion.index', ['categoria' => 'torta']))
        ->post(route('produccion.store'), $this->payload)->assertRedirect()
        ->assertSessionHasErrors('detalles.0.hora_finalizacion');
    $response = $this->withCookie(config('session.cookie'), app('session.store')->getId())
        ->get(route('produccion.index', ['categoria' => 'torta']))->assertOk()
        ->assertSeeText('Revisa los campos indicados');
    expect($response->viewData('categoriaSeleccionada'))->toBe('pan');
    assertNoPanStoreWrites();
})->with([null, '', '24:00', '08:60', '8:35', '08:35:12', 'texto', [['08:35']]]);

test('Pan exige hora incluso si se omite la propiedad', function () {
    unset($this->payload['detalles'][0]['hora_finalizacion']);
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)
        ->assertSessionHasErrors('detalles.0.hora_finalizacion');
    assertNoPanStoreWrites();
});

test('horas límite válidas se guardan en el detalle sin asignación automática', function (string $hora) {
    $this->payload['detalles'][0]['hora_finalizacion'] = $hora;
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)
        ->assertSessionHasNoErrors();
    expect(DetallePan::sole()->hora_finalizacion)->toBe($hora.':00');
    $response = $this->get(panContextUrl($this->payload))->assertOk()->assertSeeText('Hora de finalización: '.$hora);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $input = (new DOMXPath($document))->query('//input[@name="detalles[0][hora_finalizacion]"]')->item(0);
    expect($input->getAttribute('value'))->toBe('')
        ->and($input->getAttribute('type'))->toBe('hidden');
    $xpath = new DOMXPath($document);
    foreach (['hora' => 24, 'minutos' => 60] as $parte => $opciones) {
        $select = $xpath->query('//select[@id="detalle-hora-finalizacion-'.$parte.'"]')->item(0);
        expect($select->hasAttribute('required'))->toBeTrue()
            ->and($select->hasAttribute('name'))->toBeFalse()
            ->and($select->getElementsByTagName('option')->length)->toBe($opciones + 1)
            ->and($xpath->query('//select[@id="detalle-hora-finalizacion-'.$parte.'"]/option[@selected and @value=""]')->length)->toBe(1)
            ->and($xpath->query('//label[@for="detalle-hora-finalizacion-'.$parte.'"]')->length)->toBe(1);
    }
})->with(['00:00', '23:59']);

test('lote histórico sin hora se consulta y no cambia al reutilizar la cabecera', function () {
    $this->actingAs($this->fixtures['usuario'])->post(route('produccion.store'), $this->payload)
        ->assertSessionHasNoErrors();
    $cabecera = Produccion::sole();
    $historico = IsolatedMariaDb::detail(DetallePan::class, $cabecera, $this->fixtures);
    expect($historico->fresh()->hora_finalizacion)->toBeNull();
    $this->payload['detalles'][0]['hora_finalizacion'] = '09:20';
    $this->post(route('produccion.store'), $this->payload)->assertSessionHasNoErrors();
    expect(Produccion::count())->toBe(1)
        ->and($historico->fresh()->hora_finalizacion)->toBeNull()
        ->and($cabecera->detallesPan()->orderBy('id')->pluck('hora_finalizacion')->all())
        ->toBe(['08:35:00', null, '09:20:00']);
    $this->get(panContextUrl($this->payload))->assertOk()->assertSeeText('Sin hora registrada');
});
