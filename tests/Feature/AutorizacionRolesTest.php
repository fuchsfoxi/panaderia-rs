<?php

use App\Models\DetalleBocadito;
use App\Models\DetallePan;
use App\Models\DetalleTorta;
use App\Models\Produccion;
use App\Models\Rol;
use App\Models\Turno;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Ejecutar php tests/run-mariadb.php para probar autorización en MariaDB aislada.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
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

test('Encargado recibe 403 en Dashboard', function () {
    $this->fixtures['rol']->update(['nombre_roles' => 'Encargado']);
    $this->actingAs($this->fixtures['usuario'])->get('/dashboard')->assertForbidden();
});

dataset('roles funcionales', [Rol::ADMINISTRADOR, Rol::OPERADOR]);
dataset('roles denegados', ['Encargado', 'Desconocido', null]);
dataset('rutas operativas', [
    ['GET', '/dashboard'], ['GET', '/produccion'], ['GET', '/history'],
    ['POST', '/produccion'], ['POST', '/produccion/torta'], ['POST', '/produccion/bocadito'],
]);

function usuarioConRolDePrueba(array $fixtures, ?string $nombre): Usuario
{
    $usuario = $fixtures['usuario']->fresh();
    if ($nombre === null) {
        // La FK real es obligatoria; simular una relación ausente sin romper el esquema.
        $usuario->setRelation('rol', null);
    } else {
        $fixtures['rol']->update(['nombre_roles' => $nombre]);
    }

    return $usuario;
}

test('los helpers y los Gates respetan la matriz de roles', function (?string $nombre, bool $operacion, bool $administracion) {
    $usuario = usuarioConRolDePrueba($this->fixtures, $nombre);
    expect($usuario->esAdministrador())->toBe($administracion)
        ->and($usuario->esOperador())->toBe($nombre === Rol::OPERADOR)
        ->and($usuario->puedeAccederOperacion())->toBe($operacion)
        ->and(Gate::forUser($usuario)->allows('acceder-operacion'))->toBe($operacion)
        ->and(Gate::forUser($usuario)->allows('administrar-sistema'))->toBe($administracion);

    if (! $administracion) {
        expect(fn () => Gate::forUser($usuario)->authorize('administrar-sistema'))
            ->toThrow(AuthorizationException::class);
    }
})->with([
    [Rol::ADMINISTRADOR, true, true], [Rol::OPERADOR, true, false],
    ['Encargado', false, false], ['Desconocido', false, false], [null, false, false],
    ['Administrador adicional', false, false],
]);

test('un Operador con ID 1 no es Administrador y un Administrador con otro ID sí lo es', function () {
    // IDs explícitos exclusivamente en la base temporal; nunca representan permisos.
    DB::table('roles')->updateOrInsert(['id' => 1], ['nombre_roles' => Rol::OPERADOR]);
    DB::table('roles')->insert(['id' => 900001, 'nombre_roles' => Rol::ADMINISTRADOR]);
    $usuario = $this->fixtures['usuario'];
    $usuario->update(['rol_id' => 1]);
    expect(Gate::forUser($usuario->fresh())->allows('acceder-operacion'))->toBeTrue()
        ->and(Gate::forUser($usuario->fresh())->allows('administrar-sistema'))->toBeFalse();
    $usuario->update(['rol_id' => 900001]);
    expect(Gate::forUser($usuario->fresh())->allows('acceder-operacion'))->toBeTrue()
        ->and(Gate::forUser($usuario->fresh())->allows('administrar-sistema'))->toBeTrue();
});

test('ambos roles acceden por URL directa a las páginas y al menú compartido', function (string $nombre, string $ruta) {
    $usuario = usuarioConRolDePrueba($this->fixtures, $nombre);
    $response = $this->actingAs($usuario)->get($ruta)->assertOk()
        ->assertSeeText('Dashboard')->assertSeeText('Producción')->assertSeeText('Historial')
        ->assertDontSeeText('Configuración');
    if ($nombre === Rol::ADMINISTRADOR) {
        $response->assertSeeText('Productos');
    } else {
        $response->assertDontSeeText('Productos');
    }
})->with('roles funcionales')->with(['/dashboard', '/produccion', '/history']);

test('las rutas deniegan roles inválidos antes de validar o escribir y logout sigue disponible', function (?string $nombre, string $metodo, string $ruta, bool $json) {
    $usuario = usuarioConRolDePrueba($this->fixtures, $nombre);
    $this->actingAs($usuario);
    $response = $json ? $this->json($metodo, $ruta, []) : $this->call($metodo, $ruta);
    $response->assertForbidden();
    if ($json) {
        $response->assertHeader('Content-Type', 'application/json');
    } else {
        $response->assertSeeText('No tienes permisos para acceder a esta sección.')
            ->assertSeeText('Cerrar sesión')->assertDontSeeText('Volver al Dashboard')
            ->assertDontSeeText('Gate')->assertDontSeeText('Encargado');
    }
    expect(Produccion::count())->toBe(0)
        ->and(DetallePan::count())->toBe(0)->and(DetalleTorta::count())->toBe(0)
        ->and(DetalleBocadito::count())->toBe(0);
    $this->post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
})->with('roles denegados')->with('rutas operativas')->with([false, true]);

test('el invitado conserva redirección HTML y 401 JSON en todas las rutas internas', function (string $metodo, string $ruta, bool $json) {
    $response = $json ? $this->json($metodo, $ruta) : $this->call($metodo, $ruta);
    if ($json) {
        $response->assertUnauthorized();
    } else {
        $response->assertRedirect(route('login'));
    }
})->with('rutas operativas')->with([false, true]);

test('las rutas operativas usan auth y can mientras logout conserva solo auth', function () {
    foreach (['dashboard', 'produccion.index', 'produccion.store', 'produccion.torta.store', 'produccion.bocadito.store', 'history.index'] as $nombre) {
        expect(Route::getRoutes()->getByName($nombre)->gatherMiddleware())
            ->toContain('auth', 'can:acceder-operacion');
    }
    expect(Route::getRoutes()->getByName('logout')->gatherMiddleware())
        ->toContain('auth')->not->toContain('can:acceder-operacion', 'can:administrar-sistema');
});

test('la página 403 ofrece solo destinos autorizados y logout POST con protección CSRF', function (?string $nombre) {
    $usuario = usuarioConRolDePrueba($this->fixtures, $nombre);
    $this->actingAs($usuario);
    $html = view('errors.403')->render();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $enlaces = $xpath->query('//a');
    expect($enlaces->length)->toBe($usuario->puedeAccederOperacion() ? 1 : 0);
    if ($enlaces->length) {
        expect($enlaces->item(0)->getAttribute('href'))->toBe(route('dashboard'));
    }
    $form = $xpath->query('//form')->item(0);
    expect($form->getAttribute('action'))->toBe(route('logout'))
        ->and($form->getAttribute('method'))->toBe('POST')
        ->and($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1);
})->with([Rol::ADMINISTRADOR, Rol::OPERADOR, 'Encargado', 'Desconocido', null]);

test('ambos roles registran las tres familias sin inferir turno ni aceptar autor del cliente', function (string $nombre, string $familia) {
    $usuario = usuarioConRolDePrueba($this->fixtures, $nombre);
    // Una cuenta con nombre de mañana puede registrar producción del turno noche.
    $usuario->update(['username' => 'cuenta_manana_ficticia']);
    $noche = Turno::create(['nombre_turnos' => 'Noche']);
    $this->fixtures['productos']['Pan']->update(['panes_por_lata' => 12]);
    $detalle = [
        'producto_id' => $this->fixtures['productos'][$familia]->id,
        'observacion' => 'Lote de prueba',
        'participantes' => [
            ['empleado_id' => $this->fixtures['empleados'][0]->id, 'rol_produccion_id' => $this->fixtures['roles']['Maestro']->id],
            ['empleado_id' => $this->fixtures['empleados'][1]->id, 'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id],
        ],
    ];
    $detalle += $familia === 'Pan'
        ? ['coches' => 1, 'latas_adicionales' => 2, 'hora_finalizacion' => '19:10']
        : ['cantidad' => 4];
    if ($familia === 'Torta') {
        $detalle += ['forma' => 'circular', 'foto' => UploadedFile::fake()->image('torta-ficticia.jpg')];
    }
    $payload = [
        'fecha' => '2026-10-12', 'categoria' => strtolower($familia), 'turno_id' => $noche->id,
        'registrado_por_usuario_id' => 999999, 'rol_id' => 999999, 'detalles' => [$detalle],
    ];
    $ruta = $familia === 'Pan' ? '/produccion' : '/produccion/'.strtolower($familia);
    $this->actingAs($usuario)->post($ruta, $payload)->assertRedirect()->assertSessionHasNoErrors();
    $produccion = Produccion::sole();
    $modelo = match ($familia) {
        'Pan' => DetallePan::class, 'Torta' => DetalleTorta::class, default => DetalleBocadito::class,
    };
    $lote = $modelo::sole();
    expect($produccion->turno_id)->toBe($noche->id)
        ->and($produccion->registrado_por_usuario_id)->toBe($usuario->id)
        ->and($lote->registrado_por_usuario_id)->toBe($usuario->id)
        ->and($lote->cantidad)->toBe($familia === 'Pan' ? DetallePan::LATAS_POR_COCHE + 2 : 4)
        ->and($lote->empleados)->toHaveCount(2)
        ->and($usuario->fresh()->rol_id)->toBe($usuario->rol_id);
    if ($familia === 'Pan') {
        expect($lote->panes_por_lata_usado)->toBe(12)->and($lote->hora_finalizacion)->toBe('19:10:00');
    } elseif ($familia === 'Torta') {
        Storage::disk('public')->assertExists($lote->foto);
    }
    $this->get('/history')->assertOk()->assertSeeText('Lote de prueba')->assertSeeText('Noche');
})->with('roles funcionales')->with(['Pan', 'Torta', 'Bocadito']);

test('la siguiente petición revalida un cambio de rol persistido en una sesión existente', function () {
    $usuario = $this->fixtures['usuario'];
    $this->post('/login', ['username' => $usuario->username, 'password' => 'clave-ficticia-sprint4'])
        ->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertOk();
    $this->fixtures['rol']->update(['nombre_roles' => 'Encargado']);
    Auth::forgetGuards(); // Simular la nueva solicitud HTTP que recarga la identidad desde Eloquent.
    $this->get('/dashboard')->assertForbidden();
    $this->post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
});
