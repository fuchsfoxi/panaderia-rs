<?php

use App\Models\Producto;
use App\Models\Rol;
use App\Models\UnidadMedida;
use Database\Seeders\ProduccionDesarrolloSeeder;
use Database\Seeders\UnidadMedidaSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

require_once __DIR__.'/../Support/productos-fixtures.php';

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar con tests/run-mariadb.php.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR, false);
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

test('seeder adopta única Lata preservando ID equivalencia productos y detalles y es idempotente', function () {
    $legacy = $this->fixtures['unidad'];
    $legacy->update(['nombre_unidades_medida' => 'Lata']);
    historiaDeProducto($this->fixtures, 'Pan');
    $productos = Producto::orderBy('id')->get()->toArray();
    $detalles = DB::table('detalle_pan')->get()->toJson();
    (new UnidadMedidaSeeder)->run();
    $antes = UnidadMedida::orderBy('id')->get()->toArray();
    (new UnidadMedidaSeeder)->run();
    expect(UnidadMedida::orderBy('id')->get()->toArray())->toBe($antes)
        ->and(UnidadMedida::where('codigo', UnidadMedida::LATA)->sole()->id)->toBe($legacy->id)
        ->and(UnidadMedida::where('codigo', UnidadMedida::UNIDAD)->sole()->equivalencia_unidades)->toBe('1.00')
        ->and(Producto::orderBy('id')->get()->toArray())->toBe($productos)
        ->and(DB::table('detalle_pan')->get()->toJson())->toBe($detalles);
});

test('seeder bloquea coincidencias ambiguas o incompatibles sin elegir ni modificar legacy', function (string $caso) {
    $primera = UnidadMedida::create(['nombre_unidades_medida' => 'Lata', 'equivalencia_unidades' => $caso === 'incompatible' ? 30 : 1]);
    if ($caso === 'duplicada') {
        UnidadMedida::create(['nombre_unidades_medida' => ' lata ', 'equivalencia_unidades' => 1]);
    }
    $antes = UnidadMedida::whereNull('codigo')->orderBy('id')->get()->toArray();
    expect(fn () => (new UnidadMedidaSeeder)->run())->toThrow(RuntimeException::class, 'ambiguo o incompatible');
    expect(UnidadMedida::whereNull('codigo')->orderBy('id')->get()->toArray())->toBe($antes)
        ->and($primera->fresh()->codigo)->toBeNull()
        ->and(UnidadMedida::where('codigo', UnidadMedida::LATA)->count())->toBe(0)
        ->and(UnidadMedida::where('codigo', UnidadMedida::UNIDAD)->count())->toBe(1);
})->with(['duplicada', 'incompatible']);

test('codigo UNIQUE permite varias unidades históricas sin código', function () {
    (new UnidadMedidaSeeder)->run();
    UnidadMedida::create(['nombre_unidades_medida' => 'Legacy adicional']);
    expect(UnidadMedida::whereNull('codigo')->count())->toBe(2);
    expect(fn () => UnidadMedida::create(['codigo' => UnidadMedida::LATA, 'nombre_unidades_medida' => 'Duplicado']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('crea y edita por código con IDs generados y nombres modificados sin enviar unidad', function (string $familia) {
    // Desplazar IDs sin asignarlos manualmente.
    foreach (range(1, 4) as $n) {
        UnidadMedida::create(['nombre_unidades_medida' => 'Legacy '.$n]);
    }
    (new UnidadMedidaSeeder)->run();
    $unidad = UnidadMedida::where('codigo', ($familia === 'Pan' ? UnidadMedida::LATA : UnidadMedida::UNIDAD))->sole();
    $unidad->update(['nombre_unidades_medida' => 'Etiqueta modificada', 'equivalencia_unidades' => 30]);
    expect($unidad->id)->toBeGreaterThan(2);
    $datos = datosProductoDePrueba($this->fixtures, $familia);
    unset($datos['unidad_medida_id']);
    $this->post('/productos', $datos)->assertRedirect()->assertSessionHasNoErrors();
    $producto = Producto::where('nombre_p', $datos['nombre_p'])->sole();
    expect($producto->unidad_medida_id)->toBe($unidad->id);
    $this->patch('/productos/'.$producto->id, $datos)->assertRedirect()->assertSessionHasNoErrors();
    expect($producto->fresh()->unidadMedida->codigo)->toBe(($familia === 'Pan' ? UnidadMedida::LATA : UnidadMedida::UNIDAD));
})->with(['Pan', 'Torta', 'Bocadito']);

test('POST y PATCH manipulados no persisten unidad de otra familia', function (string $familia, bool $editar) {
    (new UnidadMedidaSeeder)->run();
    $producto = $this->fixtures['productos'][$familia];
    $unidad = UnidadMedida::where('codigo', ($familia === 'Pan' ? UnidadMedida::LATA : UnidadMedida::UNIDAD))->sole();
    $producto->update(['unidad_medida_id' => $unidad->id]);
    $otra = UnidadMedida::where('codigo', $familia === 'Pan' ? UnidadMedida::UNIDAD : UnidadMedida::LATA)->sole();
    $datos = datosProductoDePrueba($this->fixtures, $familia);
    $datos['unidad_medida_id'] = $otra->id;
    $antes = Producto::orderBy('id')->get()->toArray();
    $this->json($editar ? 'PATCH' : 'POST', '/productos'.($editar ? '/'.$producto->id : ''), $datos)
        ->assertUnprocessable()->assertJsonValidationErrors('unidad_medida_id');
    expect(Producto::orderBy('id')->get()->toArray())->toBe($antes);
})->with(['Pan', 'Torta', 'Bocadito'])->with([false, true]);

test('sin código canónico no guarda aunque exista un nombre coincidente', function () {
    $this->fixtures['unidad']->update(['nombre_unidades_medida' => 'Lata']);
    $this->postJson('/productos', datosProductoDePrueba($this->fixtures))
        ->assertUnprocessable()->assertJsonValidationErrors('unidad_medida_id');
    expect(Producto::count())->toBe(3);
});

test('edición histórica conserva asociación legacy y advierte sin alterar ningún detalle', function (string $familia) {
    (new UnidadMedidaSeeder)->run();
    historiaDeProducto($this->fixtures, $familia);
    $producto = $this->fixtures['productos'][$familia];
    $tabla = 'detalle_'.strtolower($familia);
    $antes = DB::table($tabla)->get()->toJson();
    $datos = datosProductoDePrueba($this->fixtures, $familia);
    unset($datos['unidad_medida_id'], $datos['unidad_registro']);
    $this->get(route('productos.edit', $producto))->assertOk()->assertSeeText('Asociación histórica conservada');
    $this->patch('/productos/'.$producto->id, $datos)->assertRedirect()->assertSessionHasNoErrors();
    expect($producto->fresh()->unidad_medida_id)->toBe($this->fixtures['unidad']->id)
        ->and(DB::table($tabla)->get()->toJson())->toBe($antes);
    $datos['unidad_medida_id'] = UnidadMedida::where('codigo', ($familia === 'Pan' ? UnidadMedida::LATA : UnidadMedida::UNIDAD))->sole()->id;
    $this->patchJson('/productos/'.$producto->id, $datos)->assertUnprocessable()->assertJsonValidationErrors('unidad_medida_id');
})->with(['Pan', 'Torta', 'Bocadito']);

test('seeder de desarrollo usa código y no duplica ejemplo al repetirse', function () {
    (new ProduccionDesarrolloSeeder)->run();
    $antes = UnidadMedida::orderBy('id')->get()->toArray();
    (new ProduccionDesarrolloSeeder)->run();
    expect(Producto::where('nombre_p', 'Pan Yema')->count())->toBe(1)
        ->and(Producto::where('nombre_p', 'Pan Yema')->sole()->unidadMedida->codigo)->toBe(UnidadMedida::LATA)
        ->and(UnidadMedida::orderBy('id')->get()->toArray())->toBe($antes);
});

test('seeder de desarrollo rechaza reasignar ejemplo con historial legacy', function () {
    $producto = $this->fixtures['productos']['Pan'];
    $producto->update(['nombre_p' => 'Pan Yema']);
    historiaDeProducto($this->fixtures, 'Pan');
    $antes = $producto->fresh()->getAttributes();
    $detalle = DB::table('detalle_pan')->get()->toJson();
    expect(fn () => (new ProduccionDesarrolloSeeder)->run())->toThrow(RuntimeException::class, 'no se reasignará');
    expect($producto->fresh()->getAttributes())->toBe($antes)
        ->and(DB::table('detalle_pan')->get()->toJson())->toBe($detalle);
});
