<?php

use App\Actions\Produccion\RegistrarProduccionPan;
use App\Models\DetallePan;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Turno;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use Database\Seeders\UnidadMedidaSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedMariaDb;

require_once __DIR__.'/../Support/productos-fixtures.php';

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    (new UnidadMedidaSeeder)->run();
    $this->pan = $this->fixtures['productos']['Pan'];
    $this->pan->update(['unidad_medida_id' => UnidadMedida::where('codigo', UnidadMedida::UNIDAD)->sole()->id, 'panes_por_lata' => null]);
    $this->datos = datosProduccionProducto($this->fixtures);
    $this->datos['detalles'][0] = array_diff_key($this->datos['detalles'][0], array_flip(['coches', 'latas_adicionales', 'hora_finalizacion', 'forma']));
    $this->datos['detalles'][0]['cantidad'] = 20;
    $this->actingAs($this->fixtures['usuario']);
    $this->withoutVite();
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

test('Pan UNIDAD guarda cantidad directa sin snapshot ni hora por HTTP y Action', function (bool $directo) {
    if ($directo) {
        app(RegistrarProduccionPan::class)->ejecutar($this->datos, $this->fixtures['usuario']->id);
    } else {
        $this->post('/produccion', $this->datos)->assertRedirect()->assertSessionHasNoErrors();
    }
    $detalle = DetallePan::sole();
    expect($detalle->cantidad)->toBe(20)->and($detalle->unidadMedida->codigo)->toBe(UnidadMedida::UNIDAD)
        ->and($detalle->panes_por_lata_usado)->toBeNull()->and($detalle->hora_finalizacion)->toBeNull()
        ->and($detalle->empleados)->toHaveCount(2)->and($detalle->produccion->categoria->nombre_categorias)->toBe('Pan');
})->with([false, true]);

test('Productos permite crear Pan UNIDAD sin factor mediante código semántico', function () {
    $this->post('/productos', ['nombre_p' => 'Pan directo ficticio', 'categoria_id' => $this->pan->categoria_id, 'unidad_registro' => 'UNIDAD'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $producto = Producto::where('nombre_p', 'Pan directo ficticio')->sole();
    expect($producto->unidadMedida->codigo)->toBe('UNIDAD')->and($producto->panes_por_lata)->toBeNull();
});

test('Dashboard cuenta latas históricas sin sumar las unidades de Pan', function () {
    $cabecera = Produccion::create(['fecha' => now()->toDateString(), 'categoria_id' => $this->pan->categoria_id, 'turno_id' => $this->fixtures['turno']->id, 'registrado_por_usuario_id' => $this->fixtures['usuario']->id]);
    foreach (['LATA' => 18, 'UNIDAD' => 20] as $codigo => $cantidad) {
        IsolatedMariaDb::detail(DetallePan::class, $cabecera, $this->fixtures, ['cantidad' => $cantidad, 'unidad_medida_id' => UnidadMedida::where('codigo', $codigo)->sole()->id]);
    }
    $this->get('/dashboard')->assertOk()->assertViewHas('totalLatasHoy', 18)->assertViewHas('totalLotesHoy', 2);
});

test('Productos valida registro semántico por familia y factor compatible', function (string $familia, string $codigo, ?int $factor, bool $valido, string $campo) {
    $datos = ['nombre_p' => 'Producto de prueba', 'categoria_id' => $this->fixtures['productos'][$familia]->categoria_id, 'unidad_registro' => $codigo, 'panes_por_lata' => $factor];
    $response = $this->postJson('/productos', $datos);
    if ($valido) {
        $response->assertRedirect()->assertSessionHasNoErrors();
        $producto = Producto::where('nombre_p', 'Producto de prueba')->sole();
        expect($producto->unidadMedida->codigo)->toBe($codigo)->and($producto->panes_por_lata)->toBe($factor);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors($campo);
        expect(Producto::where('nombre_p', 'Producto de prueba')->exists())->toBeFalse();
    }
})->with([
    ['Pan', 'LATA', 30, true, ''], ['Pan', 'LATA', null, false, 'panes_por_lata'],
    ['Pan', 'UNIDAD', null, true, ''], ['Pan', 'UNIDAD', 30, false, 'panes_por_lata'],
    ['Torta', 'UNIDAD', null, true, ''], ['Bocadito', 'UNIDAD', null, true, ''],
    ['Torta', 'LATA', null, false, 'unidad_registro'], ['Bocadito', 'LATA', null, false, 'unidad_registro'],
    ['Pan', 'CAJA', null, false, 'unidad_registro'],
]);

test('Productos rechaza IDs arbitrarios aunque el código semántico sea válido', function (string $codigo) {
    $otra = UnidadMedida::create(['nombre_unidades_medida' => 'Caja ficticia']);
    $this->postJson('/productos', ['nombre_p' => 'Manipulado', 'categoria_id' => $this->pan->categoria_id, 'unidad_registro' => $codigo, 'unidad_medida_id' => $otra->id, 'panes_por_lata' => $codigo === 'LATA' ? 30 : null])
        ->assertUnprocessable()->assertJsonValidationErrors('unidad_medida_id');
    expect(Producto::where('nombre_p', 'Manipulado')->exists())->toBeFalse();
})->with(['LATA', 'UNIDAD']);

test('Pan sin historia cambia UNIDAD a LATA con factor y vuelve a UNIDAD limpiándolo', function () {
    $base = ['nombre_p' => $this->pan->nombre_p, 'categoria_id' => $this->pan->categoria_id];
    $this->patchJson('/productos/'.$this->pan->id, $base + ['unidad_registro' => 'LATA'])->assertUnprocessable()->assertJsonValidationErrors('panes_por_lata');
    $this->patch('/productos/'.$this->pan->id, $base + ['unidad_registro' => 'LATA', 'panes_por_lata' => 30])->assertRedirect()->assertSessionHasNoErrors();
    expect($this->pan->fresh()->unidadMedida->codigo)->toBe('LATA')->and($this->pan->fresh()->panes_por_lata)->toBe(30);
    $this->patchJson('/productos/'.$this->pan->id, $base + ['unidad_registro' => 'UNIDAD', 'panes_por_lata' => 30])->assertUnprocessable()->assertJsonValidationErrors('panes_por_lata');
    $this->patch('/productos/'.$this->pan->id, $base + ['unidad_registro' => 'UNIDAD'])->assertRedirect()->assertSessionHasNoErrors();
    expect($this->pan->fresh()->unidadMedida->codigo)->toBe('UNIDAD')->and($this->pan->fresh()->panes_por_lata)->toBeNull();
});

test('Pan con historia no cambia unidad ni reescribe snapshots en ninguno de los sentidos', function (string $codigo) {
    $lata = $codigo === 'LATA';
    if ($lata) {
        $this->pan->update(['unidad_medida_id' => UnidadMedida::where('codigo', 'LATA')->sole()->id, 'panes_por_lata' => 30]);
        $this->datos['detalles'][0] = array_diff_key($this->datos['detalles'][0], ['cantidad' => true]) + ['coches' => 1, 'latas_adicionales' => 4, 'hora_finalizacion' => '19:30'];
    }
    app(RegistrarProduccionPan::class)->ejecutar($this->datos, $this->fixtures['usuario']->id);
    $antes = DetallePan::sole()->getAttributes();
    $this->patchJson('/productos/'.$this->pan->id, ['nombre_p' => $this->pan->nombre_p, 'categoria_id' => $this->pan->categoria_id, 'unidad_registro' => $lata ? 'UNIDAD' : 'LATA', 'panes_por_lata' => $lata ? null : 30])
        ->assertUnprocessable()->assertJsonValidationErrors('unidad_registro');
    expect($this->pan->fresh()->unidadMedida->codigo)->toBe($codigo)->and(DetallePan::sole()->getAttributes())->toBe($antes);
})->with(['LATA', 'UNIDAD']);

test('Pan UNIDAD rechaza cantidades inválidas y campos incompatibles por HTTP y Action', function (string $campo, mixed $valor, bool $directo) {
    $this->datos['detalles'][0][$campo] = $valor;
    if ($directo) {
        try {
            app(RegistrarProduccionPan::class)->ejecutar($this->datos, $this->fixtures['usuario']->id);
            $this->fail('Debía rechazar el registro contradictorio.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('detalles.0.'.$campo);
        }
    } else {
        $this->call('POST', '/produccion', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode($this->datos, JSON_PRESERVE_ZERO_FRACTION))->assertUnprocessable()->assertJsonValidationErrors('detalles.0.'.$campo);
    }
    expect(DetallePan::count())->toBe(0)->and(Produccion::count())->toBe(0)->and(DB::table('detalle_pan_empleado')->count())->toBe(0);
})->with([
    ['cantidad', 0], ['cantidad', -1], ['cantidad', 1.5], ['cantidad', 2.0], ['cantidad', '2.0'], ['cantidad', 2147483648], ['cantidad', null], ['cantidad', [20]], ['cantidad', true],
    ['coches', 1], ['coches', 0], ['latas_adicionales', 4], ['latas_adicionales', 0], ['hora_finalizacion', '19:30'], ['hora_finalizacion', null],
])->with([false, true]);

test('Pan LATA conserva 1 coche más 4 igual a 22 latas snapshot hora y estimación', function (bool $directo) {
    $this->pan->update(['unidad_medida_id' => UnidadMedida::where('codigo', 'LATA')->sole()->id, 'panes_por_lata' => 30]);
    $this->datos['detalles'][0] = array_diff_key($this->datos['detalles'][0], ['cantidad' => true]) + ['coches' => 1, 'latas_adicionales' => 4, 'hora_finalizacion' => '19:30'];
    if ($directo) {
        app(RegistrarProduccionPan::class)->ejecutar($this->datos, $this->fixtures['usuario']->id);
    } else {
        $this->post('/produccion', $this->datos)->assertRedirect()->assertSessionHasNoErrors();
    }
    $detalle = DetallePan::sole();
    expect(DetallePan::LATAS_POR_COCHE)->toBe(18)->and($detalle->cantidad)->toBe(22)->and($detalle->unidadMedida->codigo)->toBe('LATA')
        ->and($detalle->panes_por_lata_usado)->toBe(30)->and($detalle->cantidad * $detalle->panes_por_lata_usado)->toBe(660)->and($detalle->hora_finalizacion)->toBe('19:30:00');
})->with([false, true]);

test('Pan LATA rechaza cantidad directa y hora ausente también en Action', function (string $caso, bool $directo) {
    $this->pan->update(['unidad_medida_id' => UnidadMedida::where('codigo', 'LATA')->sole()->id, 'panes_por_lata' => 30]);
    $this->datos['detalles'][0] = array_diff_key($this->datos['detalles'][0], ['cantidad' => true]) + ['coches' => 1, 'latas_adicionales' => 4, 'hora_finalizacion' => '19:30'];
    $campo = $caso === 'cantidad' ? 'cantidad' : 'hora_finalizacion';
    if ($caso === 'cantidad') {
        $this->datos['detalles'][0]['cantidad'] = 20;
    } else {
        unset($this->datos['detalles'][0]['hora_finalizacion']);
    }
    if ($directo) {
        expect(fn () => app(RegistrarProduccionPan::class)->ejecutar($this->datos, $this->fixtures['usuario']->id))->toThrow(ValidationException::class);
    } else {
        $this->postJson('/produccion', $this->datos)->assertUnprocessable()->assertJsonValidationErrors('detalles.0.'.$campo);
    }
    expect(DetallePan::count())->toBe(0);
})->with(['cantidad', 'sin hora'])->with([false, true]);

test('Pan rechaza unidad desconocida y catálogo incompatible sin escribir', function (string $caso, bool $directo) {
    if ($caso === 'unidad') {
        $this->pan->update(['unidad_medida_id' => UnidadMedida::create(['nombre_unidades_medida' => 'Caja'])->id]);
    } else {
        $this->pan->update(['panes_por_lata' => 30]);
    }
    if ($directo) {
        expect(fn () => app(RegistrarProduccionPan::class)->ejecutar($this->datos, $this->fixtures['usuario']->id))->toThrow(ValidationException::class);
    } else {
        $this->postJson('/produccion', $this->datos)->assertUnprocessable()->assertJsonValidationErrors('detalles.0.producto_id');
    }
    expect(DetallePan::count())->toBe(0);
})->with(['unidad', 'factor de UNIDAD'])->with([false, true]);

test('ambos registros de Pan exigen Maestro y Ayudante por HTTP y Action', function (string $codigo, string $caso, bool $directo) {
    if ($codigo === 'LATA') {
        $this->pan->update(['unidad_medida_id' => UnidadMedida::where('codigo', 'LATA')->sole()->id, 'panes_por_lata' => 30]);
        $this->datos['detalles'][0] = array_diff_key($this->datos['detalles'][0], ['cantidad' => true]) + ['coches' => 1, 'latas_adicionales' => 4, 'hora_finalizacion' => '19:30'];
    }
    $this->datos['detalles'][0]['participantes'] = $caso === 'sin Maestro' ? [$this->datos['detalles'][0]['participantes'][1]] : [$this->datos['detalles'][0]['participantes'][0]];
    if ($directo) {
        expect(fn () => app(RegistrarProduccionPan::class)->ejecutar($this->datos, $this->fixtures['usuario']->id))->toThrow(ValidationException::class);
    } else {
        $this->postJson('/produccion', $this->datos)->assertUnprocessable()->assertJsonValidationErrors('detalles.0.participantes');
    }
    expect(DetallePan::count())->toBe(0);
})->with(['LATA', 'UNIDAD'])->with(['sin Maestro', 'sin Ayudante'])->with([false, true]);

test('Admin y Operador conservan reglas de turno en ambos registros y Action directa', function (string $codigo, bool $operador, bool $otroTurno, bool $directo) {
    if ($codigo === 'LATA') {
        $this->pan->update(['unidad_medida_id' => UnidadMedida::where('codigo', 'LATA')->sole()->id, 'panes_por_lata' => 30]);
        $this->datos['detalles'][0] = array_diff_key($this->datos['detalles'][0], ['cantidad' => true]) + ['coches' => 1, 'latas_adicionales' => 4, 'hora_finalizacion' => '19:30'];
    }
    $actor = $this->fixtures['usuario'];
    if ($operador) {
        $rol = Rol::create(['nombre_roles' => Rol::OPERADOR]);
        $actor = Usuario::create(['username' => 'operador.ficticio', 'rol_id' => $rol->id, 'turno_id' => $this->fixtures['turno']->id, 'password_hash' => $actor->password_hash]);
    }
    if ($otroTurno) {
        $this->datos['turno_id'] = Turno::create(['nombre_turnos' => 'Noche'])->id;
    }
    $rechazado = $operador && $otroTurno;
    if ($directo) {
        if ($rechazado) {
            expect(fn () => app(RegistrarProduccionPan::class)->ejecutar($this->datos, $actor->id))->toThrow(ValidationException::class);
        } else {
            app(RegistrarProduccionPan::class)->ejecutar($this->datos, $actor->id);
        }
    } else {
        $response = $this->actingAs($actor)->postJson('/produccion', $this->datos);
        if ($rechazado) {
            $response->assertUnprocessable()->assertJsonValidationErrors('turno_id');
        } else {
            $response->assertRedirect()->assertSessionHasNoErrors();
        }
    }
    expect(DetallePan::count())->toBe($rechazado ? 0 : 1);
    if (! $rechazado) {
        expect(DetallePan::sole()->turno_id)->toBe($this->datos['turno_id']);
    }
})->with(['LATA', 'UNIDAD'])->with([false, true])->with([false, true])->with([false, true]);

test('Historial interpreta la unidad y factor del detalle aunque cambie el catálogo actual', function () {
    $this->pan->update(['unidad_medida_id' => UnidadMedida::where('codigo', 'LATA')->sole()->id, 'panes_por_lata' => 30]);
    $lata = $this->datos;
    $lata['detalles'][0] = array_diff_key($lata['detalles'][0], ['cantidad' => true]) + ['coches' => 1, 'latas_adicionales' => 4, 'hora_finalizacion' => '19:30'];
    app(RegistrarProduccionPan::class)->ejecutar($lata, $this->fixtures['usuario']->id);
    $historico = DetallePan::sole();
    // Bypass exclusivo del fixture: demostrar independencia incluso ante un escritor ajeno al CRUD.
    $this->pan->update(['unidad_medida_id' => UnidadMedida::where('codigo', 'UNIDAD')->sole()->id, 'panes_por_lata' => null]);
    app(RegistrarProduccionPan::class)->ejecutar($this->datos, $this->fixtures['usuario']->id);
    $unidad = DetallePan::orderByDesc('id')->first();
    $html = $this->get('/history?categoria=pan')->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $texto = fn ($id) => $xpath->query('//article[@data-detalle-id="'.$id.'"]')->item(0)->textContent;
    expect($texto($historico->id))->toContain('latas', '1 coches + 4 latas adicionales', '660', '19:30', '30');
    expect($texto($unidad->id))->toContain('20', 'unidades')->not->toContain('coches', 'latas', 'Panes por lata', 'estimado', 'Hora de finalización', 'Sin hora');
    $this->get('/produccion?fecha='.$this->datos['fecha'].'&turno_id='.$this->datos['turno_id'])->assertOk()->assertSeeText('20 unidades');
    $historico->update(['hora_finalizacion' => null]);
    $this->get('/history?categoria=pan')->assertOk()->assertSeeText('Sin hora registrada');
});
