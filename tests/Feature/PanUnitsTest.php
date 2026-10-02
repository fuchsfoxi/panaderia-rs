<?php

use App\Models\DetallePan;
use App\Models\Produccion;
use App\Models\Producto;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para probar unidades de Pan en MariaDB aislada.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $this->fixtures = IsolatedMariaDb::fixtures();
    $this->produccion = Produccion::create([
        'fecha' => '2026-10-03', 'registrado_por_usuario_id' => $this->fixtures['usuario']->id,
        'categoria_id' => $this->fixtures['productos']['Pan']->categoria_id,
        'turno_id' => $this->fixtures['turno']->id,
    ]);
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

test('los parámetros desconocidos permanecen NULL sin defaults ni copia automática', function () {
    foreach ($this->fixtures['productos'] as $producto) {
        expect($producto->fresh()->panes_por_lata)->toBeNull();
    }
    $this->fixtures['productos']['Pan']->update(['panes_por_lata' => 12]);
    $detalle = IsolatedMariaDb::detail(DetallePan::class, $this->produccion, $this->fixtures);
    expect($detalle->fresh()->panes_por_lata_usado)->toBeNull();
});

test('editar el parámetro de un producto conserva el snapshot histórico de Pan', function () {
    $producto = Producto::create([
        'nombre_p' => 'Pan de prueba sin regla por nombre',
        'categoria_id' => $this->fixtures['productos']['Pan']->categoria_id,
        'unidad_medida_id' => $this->fixtures['unidad']->id,
        'panes_por_lata' => 12,
    ]);
    $antiguo = IsolatedMariaDb::detail(DetallePan::class, $this->produccion, $this->fixtures, [
        'producto_id' => $producto->id, 'cantidad' => 23, 'panes_por_lata_usado' => 12,
        'observacion' => 'Snapshot ficticio anterior',
    ]);
    $producto->update(['panes_por_lata' => 14]);
    $nuevo = IsolatedMariaDb::detail(DetallePan::class, $this->produccion, $this->fixtures, [
        'producto_id' => $producto->id, 'cantidad' => 23, 'panes_por_lata_usado' => 14,
    ]); // Copia explícita en fixture; no existe escritor HTTP ni copia automática.
    $antiguo->refresh();
    expect($producto->fresh()->panes_por_lata)->toBe(14)
        ->and($antiguo->panes_por_lata_usado)->toBe(12)
        ->and($antiguo->cantidad)->toBe(23)
        ->and($antiguo->cantidad * $antiguo->panes_por_lata_usado)->toBe(276)
        ->and($nuevo->fresh()->panes_por_lata_usado)->toBe(14)
        ->and($antiguo->observacion)->toBe('Snapshot ficticio anterior')
        ->and($antiguo->producto->is($producto))->toBeTrue()
        ->and($producto->fresh()->detallesPan->modelKeys())->toEqualCanonicalizing([$antiguo->id, $nuevo->id]);
});

test('Pan persiste total de latas como entero sin conversión a coches', function ($cantidad) {
    $detalle = IsolatedMariaDb::detail(DetallePan::class, $this->produccion, $this->fixtures, ['cantidad' => $cantidad])->fresh();
    expect($detalle->cantidad)->toBeInt()->toBe($cantidad)
        ->and($detalle->toArray()['cantidad'])->toBe($cantidad)
        ->and(DB::table('detalle_pan')->where('id', $detalle->id)->value('cantidad'))->toBe($cantidad)
        ->and($detalle->unidadMedida->is($this->fixtures['unidad']))->toBeTrue()
        ->and($detalle->turno->is($this->fixtures['turno']))->toBeTrue()
        ->and($detalle->produccion->turno->is($this->fixtures['turno']))->toBeTrue()
        ->and($detalle->produccion->categoria->is($this->fixtures['productos']['Pan']->categoria))->toBeTrue();
})->with([1, 8, 17, 18, 23, 36]);
