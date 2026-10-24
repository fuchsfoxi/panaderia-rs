<?php

use App\Actions\Personal\AdministrarEmpleados;
use App\Actions\Personal\AdministrarGrupos;
use App\Http\Requests\GuardarGrupoRequest;
use App\Models\Categoria;
use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Empleado;
use App\Models\GrupoIntegrante;
use App\Models\GrupoProduccion;
use App\Models\Produccion;
use App\Models\Rol;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere el runner MariaDB aislado.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    Categoria::create(['nombre_categorias' => 'Otra familia ficticia']);
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->admin = $this->fixtures['usuario'];
    $this->datosGrupo = [
        'nombre' => 'Equipo habitual', 'categoria_id' => $this->fixtures['productos']['Pan']->categoria_id,
        'maestro_id' => $this->fixtures['empleados'][0]->id,
        'integrantes' => [$this->fixtures['empleados'][1]->id], 'activo' => 1,
    ];
    $this->actingAs($this->admin)->withoutVite();
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

function crearGrupoPrueba(array $datos, int $actor): GrupoProduccion
{
    return app(AdministrarGrupos::class)->guardar($datos, $actor);
}

test('crea las composiciones confirmadas desde HTTP sin IDs fijos ni turnos', function (string $familia, int $cantidad) {
    $datos = $this->datosGrupo;
    $datos['categoria_id'] = $this->fixtures['productos'][$familia]->categoria_id;
    $datos['integrantes'] = $this->fixtures['empleados']->slice(1, $cantidad)->pluck('id')->all();
    if ($cantidad === 0) {
        unset($datos['integrantes']); // El formulario de Torta no selecciona checkboxes.
    }
    $this->followingRedirects()->post('/grupos', $datos + ['turno_id' => 999999, 'rol_produccion_id' => 999999])
        ->assertOk()->assertSessionHasNoErrors()->assertSeeText('Grupo creado correctamente.')
        ->assertSeeText($familia)->assertSeeText($familia === 'Pan' ? 'Sin turno fijo' : 'Solo Mañana');
    $grupo = GrupoProduccion::with(GrupoProduccion::RELACIONES_COMPOSICION)->sole();
    expect($grupo->categoria_id)->not->toBe(1)->and($grupo->maestro_id)->toBe($this->datosGrupo['maestro_id'])
        ->and($grupo->integrantes)->toHaveCount($cantidad)->and($grupo->activo)->toBeTrue()
        ->and($grupo->problemasComposicion())->toBe([])->and($grupo->getAttributes())->not->toHaveKeys(['turno_id', 'rol_produccion_id'])
        ->and($grupo->maestro->gruposComoMaestro->modelKeys())->toBe([$grupo->id])
        ->and($grupo->categoria->gruposProduccion->modelKeys())->toBe([$grupo->id]);
    foreach ($grupo->integrantes as $integrante) {
        expect($integrante->pivot)->toBeInstanceOf(GrupoIntegrante::class)
            ->and($integrante->gruposComoAyudante->modelKeys())->toBe([$grupo->id]);
    }
})->with([['Pan', 1], ['Pan', 2], ['Torta', 0], ['Bocadito', 1]]);

test('rechaza composición incompatible sin crear grupo ni integrantes', function (string $caso, string $campo) {
    $datos = $this->datosGrupo;
    switch ($caso) {
        case 'Pan sin Ayudantes': $datos['integrantes'] = [];
            break;
        case 'Torta con Ayudante': $datos['categoria_id'] = $this->fixtures['productos']['Torta']->categoria_id;
            break;
        case 'Bocadito sin Ayudante': $datos['categoria_id'] = $this->fixtures['productos']['Bocadito']->categoria_id;
            $datos['integrantes'] = [];
            break;
        case 'Bocadito dos Ayudantes': $datos['categoria_id'] = $this->fixtures['productos']['Bocadito']->categoria_id;
            $datos['integrantes'][] = $this->fixtures['empleados'][2]->id;
            break;
        case 'duplicado': $datos['integrantes'][] = $datos['integrantes'][0];
            break;
        case 'Maestro como Ayudante': $datos['integrantes'] = [$datos['maestro_id']];
            break;
        case 'responsable Ayudante': $datos['maestro_id'] = $this->fixtures['empleados'][2]->id;
            break;
        case 'Practicante': $this->fixtures['empleados'][1]->update(['rol_produccion_id' => $this->fixtures['roles']['Practicante']->id]);
            break;
        case 'Ayudante inactivo': $this->fixtures['empleados'][1]->update(['activo' => false]);
            break;
        case 'Maestro inactivo': $this->fixtures['empleados'][0]->update(['activo' => false]);
            break;
        case 'sin rol': $this->fixtures['empleados'][1]->update(['rol_produccion_id' => null]);
            break;
        case 'otra categoría': $datos['categoria_id'] = Categoria::where('nombre_categorias', 'Otra familia ficticia')->sole()->id;
            break;
    }
    $this->from('/grupos/crear')->post('/grupos', $datos)->assertRedirect('/grupos/crear')->assertSessionHasErrors($campo);
    expect(GrupoProduccion::count())->toBe(0)->and(DB::table('grupo_integrantes')->count())->toBe(0);
})->with([
    ['Pan sin Ayudantes', 'integrantes'], ['Torta con Ayudante', 'integrantes'],
    ['Bocadito sin Ayudante', 'integrantes'], ['Bocadito dos Ayudantes', 'integrantes'],
    ['duplicado', 'integrantes.0'], ['Maestro como Ayudante', 'integrantes.0'],
    ['responsable Ayudante', 'maestro_id'], ['Practicante', 'integrantes'],
    ['Ayudante inactivo', 'integrantes.0'], ['Maestro inactivo', 'maestro_id'],
    ['sin rol', 'integrantes.0'], ['otra categoría', 'categoria_id'],
]);

test('rechaza estructuras y referencias manipuladas y recupera formulario sin HTTP 500', function (string $campo, mixed $valor) {
    $datos = array_replace($this->datosGrupo, [$campo => $valor]);
    $this->from('/grupos/crear')->post('/grupos', $datos)->assertRedirect('/grupos/crear')->assertSessionHasErrors();
    $this->withCookie(config('session.cookie'), app('session.store')->getId())->get('/grupos/crear')
        ->assertOk()->assertSeeText('Revisa los datos antes de guardar.');
    expect(GrupoProduccion::count())->toBe(0);
})->with([
    ['nombre', []], ['nombre', '   '], ['nombre', str_repeat('a', 101)], ['nombre', "Equipo\nMalformado"],
    ['categoria_id', []], ['categoria_id', 999999], ['maestro_id', []], ['maestro_id', 999999],
    ['integrantes', 'incorrecto'], ['integrantes', [[1]]], ['integrantes', [999999]],
    ['integrantes', [2 => 1]], ['activo', []], ['activo', 'incorrecto'],
]);

test('editar grupo conserva otros equipos y admite empleados compartidos sin exclusividad global', function () {
    $primero = crearGrupoPrueba($this->datosGrupo, $this->admin->id);
    $segundo = crearGrupoPrueba($this->datosGrupo + ['turno_id' => 999999], $this->admin->id);
    $antesSegundo = $segundo->fresh()->getAttributes();
    $pivoteSegundo = DB::table('grupo_integrantes')->where('grupo_id', $segundo->id)->get()->toJson();
    $datos = $this->datosGrupo;
    $datos['nombre'] = 'Otro equipo';
    $datos['integrantes'] = [$this->fixtures['empleados'][2]->id];
    $this->patch('/grupos/'.$primero->id, $datos)->assertRedirect('/grupos')->assertSessionHasNoErrors();
    expect($primero->fresh()->nombre)->toBe('Otro equipo')
        ->and($primero->fresh()->integrantes->modelKeys())->toBe($datos['integrantes'])
        ->and($segundo->fresh()->getAttributes())->toBe($antesSegundo)
        ->and(DB::table('grupo_integrantes')->where('grupo_id', $segundo->id)->get()->toJson())->toBe($pivoteSegundo);
    $datos['categoria_id'] = $this->fixtures['productos']['Torta']->categoria_id;
    $datos['integrantes'] = [];
    $this->put('/grupos/'.$primero->id, $datos)->assertSessionHasNoErrors();
    expect($primero->fresh()->categoria->nombre_categorias)->toBe('Torta')->and($primero->fresh()->integrantes)->toHaveCount(0);
    $datos['categoria_id'] = $this->fixtures['productos']['Bocadito']->categoria_id;
    $this->patch('/grupos/'.$primero->id, $datos)->assertSessionHasErrors('integrantes');
    expect($primero->fresh()->categoria->nombre_categorias)->toBe('Torta');
});

test('desactiva y reactiva explícitamente sin permitir eliminación ni mutación por GET', function () {
    $grupo = crearGrupoPrueba($this->datosGrupo, $this->admin->id);
    foreach ([0, 0, 1, 1] as $activo) {
        $this->patch('/grupos/'.$grupo->id.'/estado', ['activo' => $activo, 'nombre' => 'Ignorar', 'integrantes' => []])
            ->assertRedirect('/grupos')->assertSessionHasNoErrors();
        expect($grupo->fresh()->activo)->toBe((bool) $activo)->and($grupo->fresh()->nombre)->toBe('Equipo habitual')
            ->and($grupo->fresh()->integrantes)->toHaveCount(1);
    }
    $this->delete('/grupos/'.$grupo->id)->assertStatus(405);
    $this->get('/grupos/'.$grupo->id.'/estado?activo=0')->assertStatus(405);
});

test('permite cambiar el Maestro responsable y guardar un grupo inactivo con composición válida', function () {
    $grupo = crearGrupoPrueba($this->datosGrupo, $this->admin->id);
    $otro = Empleado::create(['nombre_empleados' => 'Otro maestro ficticio',
        'rol_produccion_id' => $this->fixtures['roles']['Maestro']->id, 'activo' => true]);
    $datos = array_replace($this->datosGrupo, ['maestro_id' => $otro->id, 'activo' => 0]);
    $this->patch('/grupos/'.$grupo->id, $datos)->assertRedirect('/grupos')->assertSessionHasNoErrors();
    expect($grupo->fresh()->maestro_id)->toBe($otro->id)->and($grupo->fresh()->activo)->toBeFalse()
        ->and($grupo->fresh()->integrantes->modelKeys())->toBe($this->datosGrupo['integrantes'])
        ->and($this->admin->fresh()->empleado_id)->toBe($this->fixtures['empleados'][0]->id);
});

test('cambio de rol o estado advierte sin corregir asignaciones y bloquea reactivación hasta repararlas', function (string $persona, string $cambio) {
    $grupo = crearGrupoPrueba($this->datosGrupo, $this->admin->id);
    $empleado = $this->fixtures['empleados'][$persona === 'Maestro' ? 0 : 1];
    if ($cambio === 'estado') {
        app(AdministrarEmpleados::class)->estado($empleado->id, false, $this->admin->id);
    } else {
        app(AdministrarEmpleados::class)->guardar(['nombre_empleados' => $empleado->nombre_empleados,
            'rol_produccion_id' => $this->fixtures['roles']['Practicante']->id, 'activo' => 1], $this->admin->id, $empleado->id);
    }
    $this->get('/grupos')->assertOk()->assertSeeText('Revisar composición del grupo.');
    $edicion = $this->get('/grupos/'.$grupo->id.'/editar')->assertOk();
    if ($persona === 'Maestro' || $cambio === 'estado') {
        $edicion->assertSeeText('Asignación no válida');
    } else {
        $edicion->assertSeeText('Practicante');
    }
    expect($grupo->fresh()->activo)->toBeTrue()->and($grupo->fresh()->integrantes->modelKeys())->toBe($this->datosGrupo['integrantes']);
    $this->patch('/grupos/'.$grupo->id.'/estado', ['activo' => 0])->assertSessionHasNoErrors();
    $this->patch('/grupos/'.$grupo->id.'/estado', ['activo' => 1])->assertSessionHasErrors();
    expect($grupo->fresh()->activo)->toBeFalse();
    // Consumir el redirect de error como en el navegador antes del siguiente intento.
    $this->get('/grupos')->assertOk()->assertSeeText('Revisar composición del grupo.');
    $empleado->refresh()->update(['activo' => true, 'rol_produccion_id' => $this->fixtures['roles'][$persona]->id]);
    $this->followingRedirects()->patch('/grupos/'.$grupo->id.'/estado', ['activo' => 1])->assertSessionHasNoErrors();
    expect($grupo->fresh()->activo)->toBeTrue();
    $this->get('/grupos')->assertOk()->assertDontSeeText('Revisar composición del grupo.');
})->with(['Maestro', 'Ayudante'])->with(['rol', 'estado']);

test('no permite añadir un empleado inactivo y conserva la edición anterior', function () {
    $grupo = crearGrupoPrueba($this->datosGrupo, $this->admin->id);
    $nuevo = $this->fixtures['empleados'][2];
    $nuevo->update(['activo' => false]);
    $datos = $this->datosGrupo;
    $datos['nombre'] = 'No guardar';
    $datos['integrantes'][] = $nuevo->id;
    $this->patch('/grupos/'.$grupo->id, $datos)->assertSessionHasErrors();
    expect($grupo->fresh()->nombre)->toBe('Equipo habitual')->and($grupo->fresh()->integrantes->modelKeys())->toBe($this->datosGrupo['integrantes']);
});

dataset('rutas grupos', [
    ['GET', '/grupos'], ['GET', '/grupos/crear'], ['POST', '/grupos'], ['GET', '/grupos/%d/editar'],
    ['PATCH', '/grupos/%d'], ['PUT', '/grupos/%d'], ['PATCH', '/grupos/%d/estado'],
]);

test('Operador e invitado no acceden a grupos aun con payload válido', function (bool $invitado, string $metodo, string $ruta) {
    $grupo = crearGrupoPrueba($this->datosGrupo, $this->admin->id);
    if ($invitado) {
        auth()->forgetGuards();
    } else {
        $this->fixtures['rol']->update(['nombre_roles' => Rol::OPERADOR]);
    }
    $response = $this->call($metodo, sprintf($ruta, $grupo->id), $this->datosGrupo);
    $invitado ? $response->assertRedirect('/login') : $response->assertForbidden();
    expect(GrupoProduccion::count())->toBe(1)->and($grupo->fresh()->integrantes->modelKeys())->toBe($this->datosGrupo['integrantes']);
})->with([false, true])->with('rutas grupos');

test('Requests y Action directa rechazan cuentas sin autorización actual', function (bool $inactivo) {
    $grupo = crearGrupoPrueba($this->datosGrupo, $this->admin->id);
    if ($inactivo) {
        $this->admin->update(['activo' => false]);
    } else {
        $this->fixtures['rol']->update(['nombre_roles' => Rol::OPERADOR]);
    }
    $request = new GuardarGrupoRequest;
    $request->setUserResolver(fn () => $this->admin->fresh());
    expect($request->authorize())->toBeFalse();
    $action = app(AdministrarGrupos::class);
    expect(fn () => $action->guardar($this->datosGrupo, $this->admin->id))->toThrow(HttpException::class)
        ->and(fn () => $action->estado($grupo->id, false, $this->admin->id))->toThrow(HttpException::class)
        ->and(GrupoProduccion::count())->toBe(1)->and($grupo->fresh()->activo)->toBeTrue();
})->with([false, true]);

test('Action directa vuelve a comprobar duplicados roles y estado laboral', function (string $caso) {
    $datos = $this->datosGrupo;
    if ($caso === 'duplicado') {
        $datos['integrantes'][] = $datos['integrantes'][0];
    } elseif ($caso === 'rol') {
        $this->fixtures['empleados'][0]->update(['rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id]);
    } else {
        $this->fixtures['empleados'][1]->update(['activo' => false]);
    }
    expect(fn () => crearGrupoPrueba($datos, $this->admin->id))->toThrow(ValidationException::class)
        ->and(GrupoProduccion::count())->toBe(0)->and(DB::table('grupo_integrantes')->count())->toBe(0);
})->with(['duplicado', 'rol', 'estado']);

test('fallo al insertar segundo integrante revierte grupo y pivotes juntos', function (bool $editar) {
    $tercero = Empleado::create(['nombre_empleados' => 'Otro ayudante ficticio', 'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id, 'activo' => true]);
    $grupo = $editar ? crearGrupoPrueba(array_replace($this->datosGrupo, ['integrantes' => [$tercero->id]]), $this->admin->id) : null;
    $antes = $grupo?->fresh()->getAttributes();
    $pivotes = DB::table('grupo_integrantes')->get()->toJson();
    $datos = array_replace($this->datosGrupo, ['nombre' => 'Nombre nuevo', 'integrantes' => $this->fixtures['empleados']->slice(1)->pluck('id')->all()]);
    $dispatcher = Model::getEventDispatcher();
    Model::setEventDispatcher(clone $dispatcher);
    $intentos = 0;
    try {
        GrupoIntegrante::creating(function ($pivot) use (&$intentos) {
            if (++$intentos === 2) {
                $pivot->empleado_id = 999999;
            }
        });
        $this->from($editar ? '/grupos/'.$grupo->id.'/editar' : '/grupos/crear')
            ->call($editar ? 'PATCH' : 'POST', $editar ? '/grupos/'.$grupo->id : '/grupos', $datos)->assertSessionHasErrors('grupo');
        expect($intentos)->toBe(2)->and(GrupoProduccion::count())->toBe($editar ? 1 : 0)
            ->and(DB::table('grupo_integrantes')->get()->toJson())->toBe($pivotes);
        if ($editar) {
            expect($grupo->fresh()->getAttributes())->toBe($antes);
        }
    } finally {
        Model::setEventDispatcher($dispatcher);
    }
})->with([false, true]);

test('grupos y cambios laborales conservan exactamente detalles históricos de las tres familias', function () {
    $tablas = ['produccion'];
    foreach (['Pan' => DetallePan::class, 'Torta' => DetalleTorta::class, 'Bocadito' => DetalleBocadito::class] as $familia => $modelo) {
        $produccion = Produccion::create(['fecha' => '2026-10-21', 'categoria_id' => $this->fixtures['productos'][$familia]->categoria_id,
            'turno_id' => $this->fixtures['turno']->id, 'registrado_por_usuario_id' => $this->admin->id]);
        $detalle = IsolatedMariaDb::detail($modelo, $produccion, $this->fixtures);
        foreach ($this->fixtures['empleados']->take(2) as $i => $empleado) {
            $detalle->empleados()->attach($empleado->id, ['rol_produccion_id' => $this->fixtures['roles'][$i === 0 ? 'Maestro' : 'Ayudante']->id]);
        }
        $tablas[] = $detalle->getTable();
        $tablas[] = $detalle->getTable().'_empleado';
    }
    $antes = collect($tablas)->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->get()->toJson()]);
    $grupo = crearGrupoPrueba($this->datosGrupo, $this->admin->id);
    $this->patch('/grupos/'.$grupo->id, array_replace($this->datosGrupo, ['integrantes' => [$this->fixtures['empleados'][2]->id]]))->assertSessionHasNoErrors();
    $this->patch('/grupos/'.$grupo->id.'/estado', ['activo' => 0])->assertSessionHasNoErrors();
    app(AdministrarEmpleados::class)->guardar(['nombre_empleados' => 'Empleado ficticio', 'rol_produccion_id' => $this->fixtures['roles']['Practicante']->id, 'activo' => 0],
        $this->admin->id, $this->fixtures['empleados'][0]->id);
    foreach ($antes as $tabla => $filas) {
        expect(DB::table($tabla)->get()->toJson())->toBe($filas);
    }
    $this->get('/history')->assertOk()->assertSeeText('Maestro: Empleado ficticio');
});

test('Personal tiene tercera pestaña activa y formulario funcional sin JavaScript', function () {
    $response = $this->get('/grupos')->assertOk()->assertSeeText('No hay grupos registrados');
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//nav[@aria-label="Secciones de Personal"]/a')->length)->toBe(3)
        ->and($xpath->query('//nav[@aria-label="Secciones de Personal"]/a[@aria-current="page"]')->item(0)->textContent)->toBe('Grupos');
    $this->get('/grupos/crear')->assertOk()->assertSee('name="maestro_id"', false)->assertSee('name="integrantes[]"', false)
        ->assertSeeText('Torta: sin Ayudantes, solo Mañana.')->assertDontSee('name="turno_id"', false);
});

test('filtra y pagina por categoría conservando filtros y escapando nombres', function () {
    foreach (range(1, 16) as $n) {
        crearGrupoPrueba(array_replace($this->datosGrupo, ['nombre' => 'Pan '.str_pad((string) $n, 2, '0', STR_PAD_LEFT)]), $this->admin->id);
    }
    crearGrupoPrueba(array_replace($this->datosGrupo, ['nombre' => '<script>ejemplo</script>',
        'categoria_id' => $this->fixtures['productos']['Torta']->categoria_id, 'integrantes' => []]), $this->admin->id);
    $pagina = $this->get('/grupos?categoria=Pan')->assertOk()->viewData('grupos');
    expect($pagina->total())->toBe(16)->and($pagina->count())->toBe(15)->and($pagina->nextPageUrl())->toContain('categoria=Pan');
    expect($this->get($pagina->nextPageUrl())->assertOk()->viewData('grupos'))->toHaveCount(1);
    $this->get('/grupos?categoria=Torta')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>ejemplo', false);
});

test('filtros inválidos no redirigen ni muestran resultados', function (array $filtros) {
    crearGrupoPrueba($this->datosGrupo, $this->admin->id);
    $this->get(route('grupos.index', $filtros))->assertOk()->assertSeeText('Revisa los filtros')
        ->assertDontSeeText('Equipo habitual');
})->with([[['categoria' => ['Pan']]], [['categoria' => 'Otra']], [['page' => 0]], [['page' => [1]]], [['page' => '99999999999999999999']]]);

test('mutaciones de grupos exigen CSRF real', function (?string $token, int $status) {
    $middleware = Mockery::mock(PreventRequestForgery::class, [app(), app('encrypter')])->makePartial()->shouldAllowMockingProtectedMethods();
    $middleware->shouldReceive('runningUnitTests')->andReturnFalse();
    app()->instance(PreventRequestForgery::class, $middleware);
    $this->withSession(['_token' => 'csrf-ficticio-grupos'])->post('/grupos', $this->datosGrupo + ($token === null ? [] : ['_token' => $token]))->assertStatus($status);
    expect(GrupoProduccion::count())->toBe($status === 302 ? 1 : 0);
})->with([[null, 419], ['incorrecto', 419], ['csrf-ficticio-grupos', 302]]);

test('grupo Pan admite dos Ayudantes y Practicante visible y propuesto en producción', function () {
    $arturo = Empleado::create(['nombre_empleados' => 'Arturo', 'rol_produccion_id' => $this->fixtures['roles']['Practicante']->id, 'activo' => true]);
    $datos = array_replace($this->datosGrupo, ['integrantes' => [$this->fixtures['empleados'][1]->id, $this->fixtures['empleados'][2]->id, $arturo->id]]);
    $this->post('/grupos', $datos)->assertSessionHasNoErrors();
    $grupo = GrupoProduccion::sole();
    expect($grupo->ayudantes())->toHaveCount(2)->and($grupo->practicantes()->modelKeys())->toBe([$arturo->id])->and($grupo->problemasComposicion())->toBe([]);
    $this->get('/grupos')->assertOk()->assertSeeText('Practicantes')->assertSeeText('Arturo');
    $this->get('/grupos/'.$grupo->id.'/editar')->assertOk()->assertSeeText('Arturo')->assertSeeText('Practicante');
    $disponibles = $this->get('/produccion')->assertOk()->viewData('gruposDisponibles');
    expect($disponibles->sole()->integrantes->modelKeys())->toBe($datos['integrantes']);
});

test('Practicante adicional respeta conteos por categoría de grupos', function (string $categoria, int $ayudantes, bool $valido) {
    $practicante = Empleado::create(['nombre_empleados' => 'Aprendiz ficticio', 'rol_produccion_id' => $this->fixtures['roles']['Practicante']->id, 'activo' => true]);
    $datos = array_replace($this->datosGrupo, [
        'categoria_id' => $this->fixtures['productos'][$categoria]->categoria_id,
        'integrantes' => [...$this->fixtures['empleados']->slice(1, $ayudantes)->pluck('id')->all(), $practicante->id],
    ]);
    $respuesta = $this->post('/grupos', $datos);
    $valido ? $respuesta->assertSessionHasNoErrors() : $respuesta->assertSessionHasErrors('integrantes');
    expect(GrupoProduccion::count())->toBe($valido ? 1 : 0);
})->with([['Pan', 0, false], ['Pan', 1, true], ['Torta', 0, true], ['Torta', 1, false], ['Bocadito', 0, false], ['Bocadito', 1, true], ['Bocadito', 2, false]]);
