<?php

use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use App\Models\Turno;
use Database\Seeders\TurnoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para probar TurnoSeeder en MariaDB aislada.');
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

test('TurnoSeeder crea exactamente Mañana y Noche y repetirlo no duplica registros', function () {
    expect(Turno::count())->toBe(0);
    $this->seed(TurnoSeeder::class);
    $first = Turno::orderBy('nombre_turnos')->pluck('id', 'nombre_turnos')->all();
    $this->seed(TurnoSeeder::class);
    expect(array_keys($first))->toBe(['Mañana', 'Noche'])
        ->and(Turno::count())->toBe(2)
        ->and(Turno::orderBy('nombre_turnos')->pluck('id', 'nombre_turnos')->all())->toBe($first);
});

test('TurnoSeeder conserva turnos encontrados por nombre con IDs arbitrarios', function () {
    // IDs de fixture sin significado funcional, en orden distinto al Seeder.
    DB::table('turnos')->insert([
        ['id' => 431, 'nombre_turnos' => 'Mañana'],
        ['id' => 97, 'nombre_turnos' => 'Noche'],
    ]);
    $first = Turno::orderBy('nombre_turnos')->pluck('id', 'nombre_turnos')->all();
    $this->seed(TurnoSeeder::class);
    $this->seed(TurnoSeeder::class);
    expect(Turno::count())->toBe(2)
        ->and(Turno::orderBy('nombre_turnos')->pluck('id', 'nombre_turnos')->all())->toBe($first);
});

test('cada turno sembrado recupera solo sus cabeceras y detalles legacy de Pan', function ($nombre) {
    $fixtures = IsolatedMariaDb::fixtures();
    $this->seed(TurnoSeeder::class); // Reutiliza Mañana de la fixture y crea Noche.
    $turno = Turno::where('nombre_turnos', $nombre)->firstOrFail();
    $otroTurno = Turno::where('nombre_turnos', $nombre === 'Mañana' ? 'Noche' : 'Mañana')->firstOrFail();
    $headers = [];
    $details = [];
    foreach (['2026-10-03', '2026-10-04'] as $fecha) {
        $produccion = Produccion::create([
            'fecha' => $fecha, 'registrado_por_usuario_id' => $fixtures['usuario']->id,
            'categoria_id' => $fixtures['productos']['Pan']->categoria_id, 'turno_id' => $turno->id,
        ]);
        $detalle = IsolatedMariaDb::detail(DetallePan::class, $produccion, $fixtures, ['turno_id' => $turno->id]);
        $headers[] = $produccion->id;
        $details[] = $detalle->id;
        expect($produccion->fresh()->turno->is($turno))->toBeTrue()
            ->and($produccion->fresh()->usuario->is($fixtures['usuario']))->toBeTrue()
            ->and($detalle->fresh()->turno->is($turno))->toBeTrue();
    }
    $otherHeader = Produccion::create([
        'fecha' => '2026-10-03', 'registrado_por_usuario_id' => $fixtures['usuario']->id,
        'categoria_id' => $fixtures['productos']['Pan']->categoria_id, 'turno_id' => $otroTurno->id,
    ]);
    $otherDetail = IsolatedMariaDb::detail(DetallePan::class, $otherHeader, $fixtures, ['turno_id' => $otroTurno->id]);
    expect($turno->fresh()->producciones->modelKeys())->toEqualCanonicalizing($headers)
        ->and($turno->fresh()->detallesPan->modelKeys())->toEqualCanonicalizing($details)
        ->and($otroTurno->fresh()->producciones->modelKeys())->toBe([$otherHeader->id])
        ->and($otroTurno->fresh()->detallesPan->modelKeys())->toBe([$otherDetail->id]);
})->with(['Mañana', 'Noche']);

test('sembrar turnos de Pan permite conservar cabeceras de otras familias sin turno', function ($familia, $class) {
    $fixtures = IsolatedMariaDb::fixtures();
    $this->seed(TurnoSeeder::class);
    $produccion = Produccion::create([
        'fecha' => '2026-10-03', 'registrado_por_usuario_id' => $fixtures['usuario']->id,
        'categoria_id' => $fixtures['productos'][$familia]->categoria_id, 'turno_id' => null,
    ]);
    $detalle = IsolatedMariaDb::detail($class, $produccion, $fixtures);
    expect($produccion->fresh()->turno_id)->toBeNull()
        ->and($produccion->fresh()->turno)->toBeNull()
        ->and($detalle->produccion->is($produccion))->toBeTrue()
        ->and(Turno::where('nombre_turnos', 'Mañana')->firstOrFail()->producciones()->count())->toBe(0)
        ->and(Turno::where('nombre_turnos', 'Noche')->firstOrFail()->producciones()->count())->toBe(0);
})->with([
    'Torta' => ['Torta', DetalleTorta::class],
    'Bocadito' => ['Bocadito', DetalleBocadito::class],
]);
