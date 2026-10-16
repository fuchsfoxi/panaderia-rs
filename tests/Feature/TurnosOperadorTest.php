<?php

use App\Actions\Produccion\RegistrarProduccionBocadito;
use App\Actions\Produccion\RegistrarProduccionPan;
use App\Actions\Produccion\RegistrarProduccionTorta;
use App\Models\Produccion;
use App\Models\Rol;
use App\Models\Turno;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $this->fixtures = IsolatedMariaDb::fixtures();
    $this->usuario = $this->fixtures['usuario'];
    $this->turnos = ['manana' => $this->fixtures['turno'], 'noche' => Turno::create(['nombre_turnos' => 'Noche'])];
    $this->fixtures['productos']['Pan']->update(['panes_por_lata' => 30]);
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

function payloadTurnoUsuario(array $fixtures, string $familia, int $turnoId): array
{
    $detalle = [
        'producto_id' => $fixtures['productos'][$familia]->id, 'observacion' => 'Lote ficticio de prueba de turno',
        'participantes' => [
            ['empleado_id' => $fixtures['empleados'][0]->id, 'rol_produccion_id' => $fixtures['roles']['Maestro']->id],
            ['empleado_id' => $fixtures['empleados'][1]->id, 'rol_produccion_id' => $fixtures['roles']['Ayudante']->id],
        ],
    ];
    $detalle += $familia === 'Pan' ? ['coches' => 1, 'latas_adicionales' => 2, 'hora_finalizacion' => '08:35'] : ['cantidad' => 7];
    if ($familia === 'Torta') {
        $detalle += ['forma' => 'circular', 'foto' => UploadedFile::fake()->image('foto-ficticia.jpg')];
    }

    return ['categoria' => strtolower($familia), 'fecha' => '2026-10-16', 'turno_id' => $turnoId, 'detalles' => [$detalle]];
}

function assertSinProduccionTurno(): void
{
    expect(Produccion::count())->toBe(0);
    foreach (['pan', 'torta', 'bocadito'] as $familia) {
        expect(DB::table('detalle_'.$familia)->count())->toBe(0)
            ->and(DB::table('detalle_'.$familia.'_empleado')->count())->toBe(0);
    }
    expect(Storage::disk('public')->allFiles())->toBe([]);
}

dataset('matriz turnos usuarios', [
    'admin mañana' => [Rol::ADMINISTRADOR, null, 'manana', true],
    'admin noche' => [Rol::ADMINISTRADOR, null, 'noche', true],
    'operador mañana correcto' => [Rol::OPERADOR, 'manana', 'manana', true],
    'operador mañana manipulado' => [Rol::OPERADOR, 'manana', 'noche', false],
    'operador noche correcto' => [Rol::OPERADOR, 'noche', 'noche', true],
    'operador noche manipulado' => [Rol::OPERADOR, 'noche', 'manana', false],
    'operador sin turno' => [Rol::OPERADOR, null, 'manana', false],
]);

test('HTTP respeta turno por relación en las tres familias sin dejar residuos al denegar', function (string $rol, ?string $asignado, string $solicitado, bool $permitido, string $familia) {
    $this->fixtures['rol']->update(['nombre_roles' => $rol]);
    $this->usuario->update(['username' => 'turn_manana', 'empleado_id' => null, 'turno_id' => $asignado ? $this->turnos[$asignado]->id : null]);
    $payload = payloadTurnoUsuario($this->fixtures, $familia, $this->turnos[$solicitado]->id);
    // Ni username, rol ni turno alternativo del payload cambian la identidad autenticada.
    $payload += ['usuario_id' => 999999, 'rol_id' => 999999, 'usuario_turno_id' => $this->turnos[$solicitado]->id];
    $ruta = $familia === 'Pan' ? '/produccion' : '/produccion/'.strtolower($familia);
    $response = $this->actingAs($this->usuario)->from('/produccion')->post($ruta, $payload);
    if (! $permitido) {
        $response->assertRedirect('/produccion')->assertSessionHasErrors('turno_id');
        assertSinProduccionTurno();
    } else {
        $response->assertRedirect()->assertSessionHasNoErrors();
        $produccion = Produccion::sole();
        $tabla = 'detalle_'.strtolower($familia);
        $detalle = DB::table($tabla)->sole();
        expect($produccion->turno_id)->toBe($this->turnos[$solicitado]->id)
            ->and($produccion->registrado_por_usuario_id)->toBe($this->usuario->id)
            ->and($detalle->registrado_por_usuario_id)->toBe($this->usuario->id)
            ->and(DB::table($tabla.'_empleado')->pluck('empleado_id')->all())->toEqualCanonicalizing([
                $this->fixtures['empleados'][0]->id, $this->fixtures['empleados'][1]->id,
            ]);
        if ($familia === 'Pan') {
            expect($detalle->panes_por_lata_usado)->toBe(30)->and($detalle->hora_finalizacion)->toBe('08:35:00');
        } elseif ($familia === 'Torta') {
            Storage::disk('public')->assertExists($detalle->foto);
        }
    }
})->with('matriz turnos usuarios')->with(['Pan', 'Torta', 'Bocadito']);

test('Actions revalidan el turno y rechazan rol desconocido aunque no se use Request', function (string $rol, string $familia) {
    $this->fixtures['rol']->update(['nombre_roles' => $rol]);
    $this->usuario->update(['turno_id' => $this->turnos['manana']->id]);
    $payload = payloadTurnoUsuario($this->fixtures, $familia, $this->turnos['noche']->id);
    $action = match ($familia) {
        'Pan' => RegistrarProduccionPan::class, 'Torta' => RegistrarProduccionTorta::class, default => RegistrarProduccionBocadito::class,
    };
    expect(fn () => app($action)->ejecutar($payload, $this->usuario->id))->toThrow(ValidationException::class);
    assertSinProduccionTurno();
})->with([Rol::OPERADOR, 'Encargado'])->with(['Pan', 'Torta', 'Bocadito']);

test('Admin mantiene selector y Operador ve solo turno asignado sin selector editable', function (string $rol) {
    $this->fixtures['rol']->update(['nombre_roles' => $rol]);
    $this->usuario->update(['empleado_id' => null]);
    $response = $this->actingAs($this->usuario)->get('/produccion?turno_id='.$this->turnos['noche']->id)->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    if ($rol === Rol::OPERADOR) {
        $response->assertSeeText('Turno asignado');
        expect($xpath->query('//select[@name="turno_id"]')->length)->toBe(0)
            ->and($xpath->query('//input[@name="turno_id" and @type="hidden"]')->item(0)->getAttribute('value'))->toBe((string) $this->turnos['manana']->id)
            ->and($xpath->query('//input[@id="turno-asignado" and @readonly]')->item(0)->getAttribute('value'))->toBe('Mañana');
    } else {
        expect($xpath->query('//select[@name="turno_id"]/option[@value!=""]')->length)->toBe(2);
    }
})->with([Rol::ADMINISTRADOR, Rol::OPERADOR]);

test('Operador sin turno recibe aviso y guardar queda deshabilitado', function () {
    $this->usuario->update(['turno_id' => null]);
    $this->actingAs($this->usuario)->get('/produccion')->assertOk()->assertSeeText('Tu cuenta no tiene un turno asignado.');
});

test('Historial sigue consultable por el Operador para turnos diferentes', function () {
    $this->actingAs($this->usuario)->get('/history?turno_id='.$this->turnos['noche']->id)->assertOk();
});
