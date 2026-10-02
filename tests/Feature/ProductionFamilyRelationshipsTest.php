<?php

use App\Models\Categoria;
use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use App\Models\Turno;
use Database\Seeders\CategoriaSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para probar familias en MariaDB aislada.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

test('el seeder crea exactamente las tres familias aprobadas sin duplicarlas', function () {
    expect(Categoria::count())->toBe(0);
    $this->seed(CategoriaSeeder::class);
    $first = Categoria::orderBy('nombre_categorias')->pluck('id', 'nombre_categorias')->all();
    $this->seed(CategoriaSeeder::class);
    expect(array_keys($first))->toBe(['Bocadito', 'Pan', 'Torta'])
        ->and(Categoria::count())->toBe(3)
        ->and(Categoria::orderBy('nombre_categorias')->pluck('id', 'nombre_categorias')->all())->toBe($first);
});

test('el seeder localiza categorías por nombre y conserva IDs determinados previamente', function () {
    // ID arbitrario de fixture: no representa una identidad funcional.
    DB::table('categorias')->insert(['id' => 417, 'nombre_categorias' => 'Pan']);
    $this->seed(CategoriaSeeder::class);
    $first = Categoria::orderBy('nombre_categorias')->pluck('id', 'nombre_categorias')->all();
    $this->seed(CategoriaSeeder::class);
    expect($first['Pan'])->toBe(417)
        ->and(Categoria::count())->toBe(3)
        ->and(Categoria::orderBy('nombre_categorias')->pluck('id', 'nombre_categorias')->all())->toBe($first);
});

test('familias y turno de cabecera persisten con sus relaciones y varios detalles', function ($familia, $class, $relation) {
    $fixtures = IsolatedMariaDb::fixtures();
    // Noche es una fixture; no se carga ni se modifica el catálogo real.
    $noche = Turno::create(['nombre_turnos' => 'Noche']);
    $turno = $familia === 'Pan' ? Turno::where('nombre_turnos', 'Noche')->firstOrFail() : null;
    $producto = $fixtures['productos'][$familia];
    $categoria = Categoria::where('nombre_categorias', $familia)->firstOrFail();
    $attributes = [
        'fecha' => '2026-10-03', 'registrado_por_usuario_id' => $fixtures['usuario']->id,
        'categoria_id' => $categoria->id, 'turno_id' => $turno?->id,
    ];
    $first = Produccion::create($attributes);
    $second = Produccion::create($attributes); // Unicidad de sesiones todavía no decidida.
    foreach ([$first, $second] as $produccion) {
        $extra = $familia === 'Pan' ? ['turno_id' => $turno->id] : [];
        IsolatedMariaDb::detail($class, $produccion, $fixtures, $extra + ['observacion' => 'Nota ficticia']);
        IsolatedMariaDb::detail($class, $produccion, $fixtures, $extra);
    }
    $loaded = $first->fresh(['categoria', 'turno', $relation]);
    expect($producto->fresh()->categoria->is($categoria))->toBeTrue()
        ->and($categoria->fresh()->productos->contains($producto))->toBeTrue()
        ->and($loaded->categoria->is($categoria))->toBeTrue()
        ->and($categoria->fresh()->producciones->modelKeys())->toEqualCanonicalizing([$first->id, $second->id])
        ->and($loaded->{$relation})->toHaveCount(2)
        ->and($loaded->{$relation}->first()->observacion)->toBe('Nota ficticia')
        ->and($loaded->{$relation}->last()->observacion)->toBeNull();
    if ($familia === 'Pan') {
        expect($loaded->turno->is($noche))->toBeTrue()
            ->and($noche->fresh()->producciones->modelKeys())->toEqualCanonicalizing([$first->id, $second->id])
            ->and($noche->fresh()->detallesPan)->toHaveCount(4)
            ->and($loaded->detallesPan->first()->turno->is($noche))->toBeTrue();
    } else {
        expect($loaded->turno_id)->toBeNull()->and($loaded->turno)->toBeNull();
    }
})->with([
    'Pan' => ['Pan', DetallePan::class, 'detallesPan'],
    'Torta' => ['Torta', DetalleTorta::class, 'detallesTorta'],
    'Bocadito' => ['Bocadito', DetalleBocadito::class, 'detallesBocadito'],
]);

test('la transición permite categoría y turno de cabecera nulos', function () {
    $fixtures = IsolatedMariaDb::fixtures();
    $produccion = Produccion::create([
        'fecha' => '2026-10-03', 'registrado_por_usuario_id' => $fixtures['usuario']->id,
        'categoria_id' => null, 'turno_id' => null,
    ])->fresh();
    expect($produccion->categoria_id)->toBeNull()->and($produccion->categoria)->toBeNull()
        ->and($produccion->turno_id)->toBeNull()->and($produccion->turno)->toBeNull();
});
