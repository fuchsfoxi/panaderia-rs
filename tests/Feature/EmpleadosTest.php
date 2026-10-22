<?php

use App\Actions\Personal\AdministrarEmpleados;
use App\Models\Empleado;
use App\Models\Rol;
use App\Models\RolProduccion;
use App\Models\Usuario;
use Database\Seeders\RolProduccionSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\IsolatedMariaDb;

beforeEach(function () {
    if (! getenv('SPRINT4_TEST_SOCKET')) {
        $this->markTestSkipped('Requiere MariaDB desechable.');
    }
    IsolatedMariaDb::connect();
    expect(Artisan::call('migrate', ['--database' => 'sprint4_test', '--force' => true]))->toBe(0);
    DB::beginTransaction();
    $this->fixtures = IsolatedMariaDb::fixtures(Rol::ADMINISTRADOR);
    $this->admin = $this->fixtures['usuario'];
    $this->actingAs($this->admin)->withoutVite();
    $this->datosEmpleado = ['nombre_empleados' => 'María del Carmen', 'rol_produccion_id' => $this->fixtures['roles']['Practicante']->id, 'activo' => 1];
});

afterEach(function () {
    if (config('database.default') === 'sprint4_test') {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
});

dataset('rutas personal', [
    ['GET', '/personal'], ['GET', '/empleados'], ['GET', '/empleados/crear'],
    ['POST', '/empleados'], ['GET', '/empleados/%d/editar'],
    ['PATCH', '/empleados/%d'], ['PUT', '/empleados/%d'], ['PATCH', '/empleados/%d/estado'],
]);

test('Personal abre Usuarios y cada pestaña muestra solo su listado con la navegación activa', function () {
    $this->get('/personal')->assertRedirect('/usuarios');
    foreach (['usuarios' => 'Cuentas de acceso', 'empleados' => 'Empleados de producción'] as $pestana => $etiqueta) {
        $response = $this->get('/'.$pestana)->assertOk()->assertSeeText('Personal')
            ->assertSeeText('Administra las cuentas de acceso y el personal de producción.')
            ->assertSeeText('Usuarios')->assertSeeText('Empleados')->assertSee('aria-label="'.$etiqueta.'"', false);
        $html = new DOMDocument;
        @$html->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new DOMXPath($html);
        expect($xpath->query('//h1')->item(0)->textContent)->toBe('Personal')
            ->and($xpath->query('//nav[@aria-label="Secciones de Personal"]/a')->length)->toBe(3)
            ->and($xpath->query('//nav[@aria-label="Secciones de Personal"]/a[@aria-current="page"]')->item(0)->textContent)->toBe(ucfirst($pestana));
        $otra = $pestana === 'usuarios' ? 'Empleados de producción' : 'Cuentas de acceso';
        $response->assertDontSee('aria-label="'.$otra.'"', false);
        $response->assertSeeText($pestana === 'usuarios' ? '+ Nuevo usuario' : '+ Nuevo empleado');
    }
});

test('Operador e invitado no acceden a gestión de Personal ni mediante payload manipulado', function (bool $invitado, string $metodo, string $ruta) {
    if ($invitado) {
        auth()->forgetGuards();
    } else {
        $this->fixtures['rol']->update(['nombre_roles' => Rol::OPERADOR]);
    }
    $antes = Empleado::orderBy('id')->get()->toArray();
    $response = $this->call($metodo, sprintf($ruta, $this->fixtures['empleados'][0]->id), $this->datosEmpleado + ['rol' => Rol::ADMINISTRADOR]);
    $invitado ? $response->assertRedirect('/login') : $response->assertForbidden();
    expect(Empleado::orderBy('id')->get()->toArray())->toBe($antes);
})->with([false, true])->with('rutas personal');

test('crear y editar usan únicamente nombre rol actual estado y CSRF', function (bool $editar) {
    $url = $editar ? '/empleados/'.$this->fixtures['empleados'][0]->id.'/editar' : '/empleados/crear';
    $this->get($url)->assertOk()->assertSeeText($editar ? 'Editar empleado' : 'Nuevo empleado')
        ->assertSeeText('Maestro')->assertSeeText('Ayudante')->assertSeeText('Practicante')
        ->assertSee('name="_token"', false)->assertSee('name="nombre_empleados"', false)
        ->assertSee('name="rol_produccion_id"', false)->assertSee('name="activo"', false)
        ->assertDontSee('name="password"', false)->assertDontSee('name="turno_id"', false)
        ->assertDontSee('name="cargo_id"', false);
})->with([false, true]);

test('Administrador crea empleados Unicode sin crear ni modificar cuentas y descarta atributos ajenos', function () {
    $cuentas = Usuario::orderBy('id')->get()->toArray();
    $this->datosEmpleado['nombre_empleados'] = '  María   Núñez O’Connor-Soto  ';
    $this->post('/empleados', $this->datosEmpleado + ['cargo_id' => $this->fixtures['cargo']->id, 'numero_empleados' => 'inventado',
        'username' => 'intruso', 'password' => 'dato-ficticio', 'turno_id' => $this->fixtures['turno']->id, 'id' => 999999])
        ->assertRedirect('/empleados')->assertSessionHasNoErrors()->assertSessionHas('success');
    $empleado = Empleado::where('nombre_empleados', 'María Núñez O’Connor-Soto')->sole();
    expect($empleado->activo)->toBeTrue()->and($empleado->cargo_id)->toBeNull()
        ->and($empleado->numero_empleados)->toBeNull()->and($empleado->rolProduccion->nombre_roles_produccion)->toBe('Practicante')
        ->and(Usuario::orderBy('id')->get()->toArray())->toBe($cuentas);
});

test('cambia de Practicante a Ayudante y Maestro conservando cargo número y cuenta vinculada', function () {
    $empleado = $this->fixtures['empleados'][0];
    $empleado->update(['numero_empleados' => 'legacy-123', 'rol_produccion_id' => $this->fixtures['roles']['Practicante']->id]);
    $cuenta = $this->admin->getRawOriginal();
    foreach (['Ayudante', 'Maestro'] as $rol) {
        $this->patch('/empleados/'.$empleado->id, ['nombre_empleados' => 'Carlos Sánchez', 'rol_produccion_id' => $this->fixtures['roles'][$rol]->id, 'activo' => 1])
            ->assertRedirect('/empleados')->assertSessionHasNoErrors();
        $empleado->refresh();
        expect($empleado->nombre_empleados)->toBe('Carlos Sánchez')
            ->and($empleado->rolProduccion->nombre_roles_produccion)->toBe($rol)
            ->and($empleado->cargo_id)->toBe($this->fixtures['cargo']->id)->and($empleado->numero_empleados)->toBe('legacy-123')
            ->and($this->admin->fresh()->getRawOriginal())->toBe($cuenta);
    }
});

test('activar y desactivar es explícito idempotente y no desconecta la cuenta vinculada', function () {
    $empleado = $this->fixtures['empleados'][0];
    foreach ([0, 0, 1, 1] as $estado) {
        $this->patch('/empleados/'.$empleado->id.'/estado', ['activo' => $estado, 'nombre_empleados' => 'No cambiar', 'rol_produccion_id' => 999999])
            ->assertRedirect('/empleados')->assertSessionHasNoErrors();
        expect($empleado->fresh()->activo)->toBe((bool) $estado)
            ->and($empleado->fresh()->nombre_empleados)->toBe($empleado->nombre_empleados)
            ->and($this->admin->fresh()->activo)->toBeTrue();
        $this->get('/usuarios')->assertOk();
    }
});

test('no hay eliminación física ni mutaciones por GET', function () {
    $empleado = $this->fixtures['empleados'][0];
    $this->delete('/empleados/'.$empleado->id)->assertStatus(405);
    $this->get('/empleados/'.$empleado->id.'/estado?activo=0')->assertStatus(405);
    expect($empleado->fresh()->activo)->toBeTrue();
});

test('rechaza nombres roles y estados inválidos sin crear registros', function (string $campo, mixed $valor) {
    $datos = array_replace($this->datosEmpleado, [$campo => $valor]);
    $this->from('/empleados/crear')->post('/empleados', $datos)->assertRedirect('/empleados/crear')->assertSessionHasErrors($campo);
    $this->assertDatabaseCount('empleados', 3);
})->with([
    ['nombre_empleados', ''], ['nombre_empleados', '   '], ['nombre_empleados', '123456'],
    ['nombre_empleados', '<script>alert(1)</script>'], ['nombre_empleados', "Ana\nMaría"],
    ['nombre_empleados', ['array']], ['nombre_empleados', str_repeat('á', 101)],
    ['rol_produccion_id', null], ['rol_produccion_id', 999999], ['rol_produccion_id', ['array']],
    ['activo', 'invalido'], ['activo', ['array']],
]);

test('roles de acceso y cargos no se aceptan como roles actuales', function () {
    $otro = RolProduccion::create(['nombre_roles_produccion' => 'Administrador']);
    $this->post('/empleados', array_replace($this->datosEmpleado, ['rol_produccion_id' => $otro->id]))->assertSessionHasErrors('rol_produccion_id');
    $this->assertDatabaseCount('empleados', 3);
});

test('los homónimos admitidos por el esquema se mantienen como personas diferentes', function () {
    $this->datosEmpleado['nombre_empleados'] = 'Ana María';
    foreach ([1, 2] as $n) {
        $this->post('/empleados', $this->datosEmpleado)->assertRedirect('/empleados')->assertSessionHasNoErrors();
    }
    $personas = Empleado::where('nombre_empleados', 'Ana María')->get();
    expect($personas)->toHaveCount(2)->and($personas->pluck('id')->unique())->toHaveCount(2);
    $response = $this->get('/empleados')->assertOk();
    foreach ($personas as $persona) {
        $response->assertSeeText('#'.$persona->id);
    }
});

test('paginación de empleados limita quince y mantiene orden estable por nombre e ID', function () {
    foreach (range(1, 15) as $n) {
        Empleado::create(['nombre_empleados' => 'Persona '.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'rol_produccion_id' => $this->fixtures['roles']['Ayudante']->id, 'activo' => true]);
    }
    $pagina = $this->get('/empleados')->assertOk()->viewData('empleados');
    expect($pagina->total())->toBe(18)->and($pagina->count())->toBe(15);
    expect($this->get('/empleados?page=2')->assertOk()->viewData('empleados')->count())->toBe(3);
});

test('empleado legacy sin rol es visible en Personal y puede configurarse explícitamente', function () {
    $empleado = $this->fixtures['empleados'][0];
    $empleado->update(['rol_produccion_id' => null]);
    $this->get('/empleados')->assertOk()->assertSeeText('Pendiente de asignar');
    $this->patch('/empleados/'.$empleado->id, $this->datosEmpleado)->assertRedirect('/empleados')->assertSessionHasNoErrors();
    expect($empleado->fresh()->rol_produccion_id)->toBe($this->datosEmpleado['rol_produccion_id']);
});

test('mutaciones exigen CSRF real aun para el Administrador', function (?string $token, int $status) {
    $middleware = Mockery::mock(PreventRequestForgery::class, [app(), app('encrypter')])->makePartial()->shouldAllowMockingProtectedMethods();
    $middleware->shouldReceive('runningUnitTests')->andReturnFalse();
    app()->instance(PreventRequestForgery::class, $middleware);
    $this->withSession(['_token' => 'csrf-ficticio-personal'])
        ->post('/empleados', $this->datosEmpleado + ($token === null ? [] : ['_token' => $token]))->assertStatus($status);
    $this->assertDatabaseCount('empleados', $status === 302 ? 4 : 3);
})->with([[null, 419], ['incorrecto', 419], ['csrf-ficticio-personal', 302]]);

test('Action revalida al actor si la cuenta perdió permisos o fue desactivada', function (bool $inactivo) {
    if ($inactivo) {
        $this->admin->update(['activo' => false]);
    } else {
        $this->fixtures['rol']->update(['nombre_roles' => Rol::OPERADOR]);
    }
    expect(fn () => app(AdministrarEmpleados::class)->guardar($this->datosEmpleado, $this->admin->id))
        ->toThrow(HttpException::class);
    $this->assertDatabaseCount('empleados', 3);
})->with([false, true]);

test('el seeder selectivo completa Practicante sin duplicar roles ni crear personas o cuentas', function () {
    $ids = $this->fixtures['roles']->except('Practicante')->pluck('id', 'nombre_roles_produccion')->all();
    $this->fixtures['roles']['Practicante']->delete();
    $this->seed(RolProduccionSeeder::class);
    $primera = RolProduccion::orderBy('id')->get()->toArray();
    $this->seed(RolProduccionSeeder::class);
    expect(RolProduccion::orderBy('id')->get()->toArray())->toBe($primera)
        ->and(RolProduccion::where('nombre_roles_produccion', 'Practicante')->count())->toBe(1);
    foreach ($ids as $nombre => $id) {
        expect(RolProduccion::where('nombre_roles_produccion', $nombre)->sole()->id)->toBe($id);
    }
    $this->assertDatabaseCount('empleados', 3);
    $this->assertDatabaseCount('usuarios_sistema', 1);
});

test('Operador con CSRF válido sigue sin poder administrar trabajadores', function () {
    $this->fixtures['rol']->update(['nombre_roles' => Rol::OPERADOR]);
    $middleware = Mockery::mock(PreventRequestForgery::class, [app(), app('encrypter')])->makePartial()->shouldAllowMockingProtectedMethods();
    $middleware->shouldReceive('runningUnitTests')->andReturnFalse();
    app()->instance(PreventRequestForgery::class, $middleware);
    $this->withSession(['_token' => 'csrf-ficticio-personal'])
        ->post('/empleados', $this->datosEmpleado + ['_token' => 'csrf-ficticio-personal'])->assertForbidden();
    $this->assertDatabaseCount('empleados', 3);
});
