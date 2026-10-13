<?php

use App\Models\Categoria;
use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\UnidadMedida;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IsolatedMariaDb;

require_once __DIR__.'/../Support/productos-fixtures.php';

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar con el runner MariaDB aislado.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->withoutVite();
    $this->actingAs($this->fixtures['usuario']);
    Storage::fake('public');
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

test('Administrador abre el módulo Productos', function () {
    $this->get('/productos')->assertOk()->assertSeeText('Administra los productos disponibles para producción.');
});

dataset('endpoints productos', [
    ['GET', '/productos'], ['GET', '/productos/crear'], ['GET', '/productos/%s/editar'],
    ['POST', '/productos'], ['PATCH', '/productos/%s'], ['PUT', '/productos/%s'], ['PATCH', '/productos/%s/estado'],
]);

test('Operador recibe 403 por URL o payload válido sin mutar el catálogo', function (string $metodo, string $ruta, bool $json) {
    $this->fixtures['rol']->update(['nombre_roles' => Rol::OPERADOR]);
    $antes = Producto::orderBy('id')->get()->toArray();
    $ruta = sprintf($ruta, $this->fixtures['productos']['Pan']->id);
    $datos = str_ends_with($ruta, '/estado') ? ['activo' => false] : datosProductoDePrueba($this->fixtures);
    ($json ? $this->json($metodo, $ruta, $datos) : $this->call($metodo, $ruta, $datos))->assertForbidden();
    expect(Producto::orderBy('id')->get()->toArray())->toBe($antes);
})->with('endpoints productos')->with([false, true]);

test('invitado recibe login HTML o 401 JSON en cada endpoint de Productos', function (string $metodo, string $ruta, bool $json) {
    Auth::logout();
    $ruta = sprintf($ruta, $this->fixtures['productos']['Pan']->id);
    $response = $json ? $this->json($metodo, $ruta, datosProductoDePrueba($this->fixtures)) : $this->call($metodo, $ruta, datosProductoDePrueba($this->fixtures));
    $json ? $response->assertUnauthorized() : $response->assertRedirect(route('login'));
})->with('endpoints productos')->with([false, true]);

test('las rutas de Productos utilizan auth can y no exponen eliminación', function () {
    foreach (['index', 'create', 'store', 'edit', 'update', 'estado'] as $accion) {
        expect(Route::getRoutes()->getByName('productos.'.$accion)->gatherMiddleware())->toContain('auth', 'can:administrar-sistema');
    }
    $this->delete('/productos/'.$this->fixtures['productos']['Pan']->id)->assertStatus(405);
    expect(Producto::count())->toBe(3);
});

test('Operador con CSRF real válido sigue recibiendo 403 en las mutaciones', function (string $metodo, string $sufijo) {
    $middleware = Mockery::mock(PreventRequestForgery::class, [app(), app('encrypter')])->makePartial()->shouldAllowMockingProtectedMethods();
    $middleware->shouldReceive('runningUnitTests')->andReturnFalse();
    app()->instance(PreventRequestForgery::class, $middleware);
    $this->fixtures['rol']->update(['nombre_roles' => Rol::OPERADOR]);
    $this->withSession(['_token' => 'token-ficticio-productos']);
    $ruta = '/productos'.sprintf($sufijo, $this->fixtures['productos']['Pan']->id);
    $datos = str_ends_with($ruta, '/estado') ? ['activo' => 0] : datosProductoDePrueba($this->fixtures);
    $this->call($metodo, $ruta, $datos + ['_token' => 'token-ficticio-productos'])->assertForbidden();
    expect(Producto::count())->toBe(3)->and($this->fixtures['productos']['Pan']->fresh()->activo)->toBeTrue();
})->with([['POST', ''], ['PATCH', '/%s'], ['PATCH', '/%s/estado']]);

test('sidebar tiene cuatro módulos para Admin y tres para Operador', function (string $rol, int $cantidad) {
    $this->fixtures['rol']->update(['nombre_roles' => $rol]);
    $ruta = $rol === Rol::ADMINISTRADOR ? '/productos' : '/dashboard';
    $response = $this->get($ruta)->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//nav[@aria-label="Navegación principal"]/a')->length)->toBe($cantidad);
    $links = $xpath->query('//nav[@aria-label="Navegación principal"]/a[@href="'.route('productos.index').'"]');
    expect($links->length)->toBe($rol === Rol::ADMINISTRADOR ? 1 : 0);
    if ($links->length) {
        expect($links->item(0)->getAttribute('aria-current'))->toBe('page');
    }
    $response->assertDontSeeText('Configuración')->assertDontSeeText('Empleados')->assertDontSeeText('Usuarios');
})->with([[Rol::ADMINISTRADOR, 4], [Rol::OPERADOR, 3]]);

test('crea Pan con factor positivo o desconocido y normaliza nombre sin aceptar atributos extra', function (mixed $factor) {
    $datos = datosProductoDePrueba($this->fixtures);
    $datos['nombre_p'] = "  Pan\u{00a0}   Francés  ";
    $datos['panes_por_lata'] = $factor;
    $this->post('/productos', $datos + ['activo' => false, 'temporada_fe' => '2026-12-01', 'id' => 999999])
        ->assertRedirect(route('productos.index'))->assertSessionHasNoErrors();
    $producto = Producto::where('nombre_p', 'Pan Francés')->sole();
    expect($producto->panes_por_lata)->toBe($factor === null || $factor === '' ? null : (int) $factor)
        ->and($producto->activo)->toBeTrue()->and($producto->temporada_fe)->toBeNull()
        ->and($producto->categoria_id)->toBe($datos['categoria_id'])->and($producto->unidad_medida_id)->toBe($datos['unidad_medida_id'])
        ->and($producto->id)->not->toBe(999999);
})->with([null, '', 1, '24', 2147483647]);

test('crea Torta y Bocadito sin factor y rechaza un factor oculto en ambas familias', function (string $familia) {
    $datos = datosProductoDePrueba($this->fixtures, $familia);
    $this->post('/productos', $datos)->assertRedirect()->assertSessionHasNoErrors();
    expect(Producto::where('nombre_p', 'Producto nuevo')->sole()->panes_por_lata)->toBeNull();
    $datos['panes_por_lata'] = 24;
    $this->postJson('/productos', $datos)->assertUnprocessable()->assertJsonValidationErrors('panes_por_lata');
    expect(Producto::count())->toBe(4);
})->with(['Torta', 'Bocadito']);

test('crear y editar rechazan campos malformados o referencias inexistentes', function (string $campo, mixed $valor, bool $editar) {
    $datos = datosProductoDePrueba($this->fixtures);
    $datos[$campo] = $valor;
    $antes = Producto::orderBy('id')->get()->toArray();
    $ruta = '/productos'.($editar ? '/'.$this->fixtures['productos']['Pan']->id : '');
    $this->call($editar ? 'PATCH' : 'POST', $ruta, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode($datos, JSON_PRESERVE_ZERO_FRACTION))
        ->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(Producto::orderBy('id')->get()->toArray())->toBe($antes);
})->with([
    ['nombre_p', ''], ['nombre_p', '   '], ['nombre_p', ['texto']], ['nombre_p', str_repeat('é', 101)],
    ['nombre_p', "Nombre\0inválido"], ['categoria_id', null], ['categoria_id', 999999], ['categoria_id', [1]],
    ['unidad_medida_id', null], ['unidad_medida_id', 999999], ['unidad_medida_id', [1]],
    ['panes_por_lata', 0], ['panes_por_lata', -1], ['panes_por_lata', '20.5'], ['panes_por_lata', '20.0'],
    ['panes_por_lata', '1e2'], ['panes_por_lata', 1.0], ['panes_por_lata', true], ['panes_por_lata', [24]], ['panes_por_lata', 2147483648],
])->with([false, true]);

test('una categoría existente ajena a las tres familias no se puede asignar', function () {
    $categoria = Categoria::create(['nombre_categorias' => 'Otra']);
    $datos = datosProductoDePrueba($this->fixtures);
    $datos['categoria_id'] = $categoria->id;
    $this->postJson('/productos', $datos)->assertUnprocessable()->assertJsonValidationErrors('categoria_id');
});

test('nombres de 100 caracteres y repetidos se permiten de forma consistente', function () {
    $datos = datosProductoDePrueba($this->fixtures);
    $datos['nombre_p'] = str_repeat('é', 100);
    $this->post('/productos', $datos)->assertRedirect()->assertSessionHasNoErrors();
    $this->post('/productos', $datos)->assertRedirect()->assertSessionHasNoErrors();
    $this->patch('/productos/'.$this->fixtures['productos']['Pan']->id, $datos)->assertRedirect()->assertSessionHasNoErrors();
    expect(Producto::where('nombre_p', $datos['nombre_p'])->count())->toBe(3);
});

test('producto sin producción cambia categoría unidad y limpia su factor preservando temporada', function () {
    $producto = $this->fixtures['productos']['Pan'];
    $producto->update(['panes_por_lata' => 20, 'temporada_fe' => '2026-12-01']);
    $unidad = UnidadMedida::create(['nombre_unidades_medida' => 'Otra unidad', 'equivalencia_unidades' => 1]);
    $datos = datosProductoDePrueba($this->fixtures, 'Torta');
    $datos['unidad_medida_id'] = $unidad->id;
    $this->put('/productos/'.$producto->id, $datos + ['temporada_fe' => '2027-01-01', 'activo' => false])->assertRedirect()->assertSessionHasNoErrors();
    expect($producto->fresh()->categoria_id)->toBe($datos['categoria_id'])->and($producto->fresh()->unidad_medida_id)->toBe($unidad->id)
        ->and($producto->fresh()->panes_por_lata)->toBeNull()->and($producto->fresh()->temporada_fe)->toBe('2026-12-01')
        ->and($producto->fresh()->activo)->toBeTrue();
});

test('cualquier familia con historia bloquea categoría y unidad antes de escribir', function (string $familia, string $campo) {
    historiaDeProducto($this->fixtures, $familia);
    $producto = $this->fixtures['productos'][$familia];
    $antes = $producto->fresh()->getAttributes();
    $datos = datosProductoDePrueba($this->fixtures, $familia);
    if ($campo === 'categoria_id') {
        $datos[$campo] = $this->fixtures['productos'][$familia === 'Pan' ? 'Torta' : 'Bocadito']->categoria_id;
        if ($datos[$campo] === $producto->categoria_id) {
            $datos[$campo] = $this->fixtures['productos']['Pan']->categoria_id;
        }
        $datos['panes_por_lata'] = null;
    } else {
        $datos[$campo] = UnidadMedida::create(['nombre_unidades_medida' => 'Otra unidad', 'equivalencia_unidades' => 1])->id;
    }
    $this->patchJson('/productos/'.$producto->id, $datos)->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect($producto->fresh()->getAttributes())->toBe($antes);
})->with(['Pan', 'Torta', 'Bocadito'])->with(['categoria_id', 'unidad_medida_id']);

test('formulario de edición protege selects históricos y conserva nombre factor y errores asociados', function () {
    historiaDeProducto($this->fixtures, 'Pan');
    $response = $this->get('/productos/'.$this->fixtures['productos']['Pan']->id.'/editar')->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//select[@disabled]')->length)->toBe(2)
        ->and($xpath->query('//input[@type="hidden" and @name="categoria_id"]')->length)->toBe(1)
        ->and($xpath->query('//input[@type="hidden" and @name="unidad_medida_id"]')->length)->toBe(1)
        ->and($xpath->query('//input[@name="nombre_p" and not(@disabled)]')->length)->toBe(1)
        ->and($xpath->query('//input[@name="panes_por_lata" and not(@disabled)]')->length)->toBe(1);
    $response->assertSeeText('Editar el nombre también cambia el nombre visible en producciones anteriores.');
});

test('editar nombre y factor histórico cambia solo factores de lotes futuros', function () {
    $producto = $this->fixtures['productos']['Pan'];
    $producto->update(['panes_por_lata' => 20]);
    $datosProduccion = datosProduccionProducto($this->fixtures);
    $this->post('/produccion', $datosProduccion)->assertRedirect()->assertSessionHasNoErrors();
    $anterior = DetallePan::sole()->getAttributes();
    $datos = datosProductoDePrueba($this->fixtures);
    $datos['nombre_p'] = 'Pan renombrado';
    $this->patch('/productos/'.$producto->id, $datos)->assertRedirect()->assertSessionHasNoErrors();
    expect(DetallePan::sole()->getAttributes())->toBe($anterior);
    $this->get('/history')->assertOk()->assertSeeText('Pan renombrado')->assertSeeText('Panes por lata utilizado: 20');
    $this->post('/produccion', $datosProduccion)->assertRedirect()->assertSessionHasNoErrors();
    expect(DetallePan::orderBy('id')->pluck('panes_por_lata_usado')->all())->toBe([20, 24]);
    $this->get('/history')->assertOk()->assertSeeText('Panes por lata utilizado: 20')->assertSeeText('Panes por lata utilizado: 24');
});

test('desactivar excluye opciones y rechaza ID manipulado sin perder historia y reactivar permite registro', function (string $familia) {
    $producto = $this->fixtures['productos'][$familia];
    $rutaRegistro = $familia === 'Pan' ? '/produccion' : '/produccion/'.strtolower($familia);
    $datos = datosProduccionProducto($this->fixtures, $familia);
    $modelo = match ($familia) {
        'Pan' => DetallePan::class, 'Torta' => DetalleTorta::class, default => DetalleBocadito::class
    };
    $this->post($rutaRegistro, $datos)->assertRedirect()->assertSessionHasNoErrors();
    $antes = $modelo::sole()->getAttributes();
    $this->patch('/productos/'.$producto->id.'/estado', ['activo' => 0, 'nombre_p' => 'No aceptar'])->assertRedirect()->assertSessionHasNoErrors();
    expect($producto->fresh()->activo)->toBeFalse()->and($producto->fresh()->nombre_p)->toBe($producto->nombre_p);
    $response = $this->get('/produccion?categoria='.strtolower($familia))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//select[@data-control="producto_id"]/option[@value="'.$producto->id.'"]')->length)->toBe(0);
    $this->postJson($rutaRegistro, $datos)->assertUnprocessable()->assertJsonValidationErrors('detalles.0.producto_id');
    $this->get('/history')->assertOk()->assertSeeText($producto->nombre_p)->assertSeeText('Historia conservada');
    expect($modelo::sole()->getAttributes())->toBe($antes)->and(Producto::count())->toBe(3);
    $this->patch('/productos/'.$producto->id.'/estado', ['activo' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $this->post($rutaRegistro, $datos)->assertRedirect()->assertSessionHasNoErrors();
    expect($modelo::count())->toBe(2)->and($producto->fresh()->activo)->toBeTrue();
})->with(['Pan', 'Torta', 'Bocadito']);

test('cambiar estado exige boolean y la asignación repetida es idempotente', function () {
    $producto = $this->fixtures['productos']['Pan'];
    $this->patchJson('/productos/'.$producto->id.'/estado', ['activo' => 'talvez'])->assertUnprocessable()->assertJsonValidationErrors('activo');
    $this->patchJson('/productos/'.$producto->id.'/estado', [])->assertUnprocessable()->assertJsonValidationErrors('activo');
    foreach (range(1, 2) as $i) {
        $this->patch('/productos/'.$producto->id.'/estado?estado=activos&page=2', ['activo' => 0])
            ->assertRedirect(route('productos.index', ['estado' => 'activos', 'page' => 2]));
        expect($producto->fresh()->activo)->toBeFalse();
    }
});

test('búsqueda literal categoría y estado filtran en servidor y paginación conserva filtros', function () {
    $base = datosProductoDePrueba($this->fixtures);
    foreach (range(1, 17) as $n) {
        Producto::create(array_replace($base, ['nombre_p' => 'Pan especial '.str_pad((string) $n, 2, '0', STR_PAD_LEFT)]));
    }
    Producto::create(array_replace($base, ['nombre_p' => 'Pan especial inactivo', 'activo' => false]));
    Producto::create(array_replace(datosProductoDePrueba($this->fixtures, 'Torta'), ['nombre_p' => 'Pan especial Torta']));
    $filtros = ['q' => 'Pan especial', 'categoria_id' => $base['categoria_id'], 'estado' => 'activos'];
    $response = $this->get('/productos?'.http_build_query($filtros))->assertOk()
        ->assertSeeText('17 productos')->assertSeeText('Pan especial 01')->assertDontSeeText('Pan especial 16')
        ->assertDontSeeText('Pan especial inactivo')->assertDontSeeText('Pan especial Torta');
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $href = (new DOMXPath($dom))->query('//a[@rel="next"]')->item(0)->getAttribute('href');
    parse_str(parse_url($href, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray($filtros)->and($query['page'])->toBe('2');
    $this->get($href)->assertOk()->assertSeeText('Pan especial 16')->assertSeeText('Pan especial 17')->assertDontSeeText('Pan especial 01');
    $this->get('/productos?estado=inactivos')->assertOk()->assertSeeText('Pan especial inactivo')->assertDontSeeText('Pan especial 01');
    Producto::create(array_replace($base, ['nombre_p' => 'Oferta 10%_! real']));
    $this->get('/productos?q='.urlencode('%_!'))->assertOk()->assertSeeText('Oferta 10%_! real')->assertSeeText('1 producto');
});

test('filtros GET malformados se muestran sin redirección ni errores 500', function (string $query) {
    $this->get('/productos?'.$query)->assertOk()->assertSeeText('La consulta contiene filtros no válidos.')
        ->assertDontSeeText('Pan ficticio');
})->with(['q[]=dato', 'categoria_id[]=1', 'categoria_id=999999', 'estado[]=activos', 'estado=otro', 'page[]=1', 'page=0', 'page=2147483648']);

test('old input malformado no rompe el formulario y los errores tienen labels y asociaciones', function () {
    $this->from('/productos/crear')->post('/productos', ['nombre_p' => ['dato'], 'categoria_id' => ['dato'], 'unidad_medida_id' => ['dato'], 'panes_por_lata' => ['dato']])
        ->assertRedirect('/productos/crear')->assertSessionHasErrors(['nombre_p', 'categoria_id', 'unidad_medida_id', 'panes_por_lata']);
    $response = $this->withCookie(config('session.cookie'), app('session.store')->getId())->get('/productos/crear')->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($dom);
    foreach (['nombre-producto', 'categoria-producto', 'unidad-producto', 'factor-producto'] as $id) {
        expect($xpath->query('//label[@for="'.$id.'"]')->length)->toBe(1);
        $campo = $xpath->query('//*[@id="'.$id.'"]')->item(0);
        expect($campo->getAttribute('aria-invalid'))->toBe('true', $id);
        foreach (explode(' ', trim($campo->getAttribute('aria-describedby'))) as $ref) {
            if ($ref !== '') {
                expect($xpath->query('//*[@id="'.$ref.'"]')->length)->toBe(1);
            }
        }
    }
});

test('formulario avisa cuando el catálogo no permite crear y no presupone unidad Lata', function () {
    $this->get('/productos/crear')->assertOk()->assertSeeText('Unidad ficticia');
    Producto::query()->delete(); // Solo fixtures aisladas sin historia, para simular catálogo incompleto.
    UnidadMedida::query()->delete();
    $this->get('/productos/crear')->assertOk()->assertSeeText('Para guardar un producto necesitas categorías y unidades de medida disponibles.');
});

test('listado mantiene conteo de consultas al crecer y carga categoría y unidad sin N más uno', function () {
    $medir = function (): array {
        $this->fixtures['usuario']->unsetRelation('rol')->load('empleado');
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->get('/productos')->assertOk();

            return DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
    };
    $inicial = $medir();
    foreach (range(1, 20) as $n) {
        Producto::create(array_replace(datosProductoDePrueba($this->fixtures), ['nombre_p' => 'Producto '.str_repeat('largo ', 8).$n]));
    }
    $final = $medir();
    expect(count($final))->toBe(count($inicial));
    expect(collect($final)->filter(fn ($q) => str_contains($q['query'], 'from `unidades_medida`')))->toHaveCount(1)
        ->and(collect($final)->filter(fn ($q) => str_contains($q['query'], 'from `categorias`') && str_contains($q['query'], ' in ')))->toHaveCount(1);
});
