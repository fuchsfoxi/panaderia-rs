<?php

use App\Models\UsuarioSistema;
use Illuminate\Support\Facades\Hash;

/**
 * Pruebas de humo del login.
 *
 * Son SIN base de datos a proposito: esta maquina no tiene el driver
 * pdo_sqlite, asi que phpunit.xml apunta a sqlite :memory: y cualquier prueba
 * que consulte la base no puede correr. Estas verifican el cableado (rutas,
 * middleware, redirecciones, validacion y logout), que es lo que se rompio.
 *
 * El login con credenciales reales y el limite de intentos se prueban a mano
 * contra el servidor: ver README, seccion "Pruebas manuales".
 */

/**
 * Usuario SOLO en memoria, sin guardar en la base.
 *
 * forceFill() se usa porque UsuarioSistema no tiene $fillable (viene $guarded
 * = ['*'] por defecto): con new UsuarioSistema([...]) Laravel lanzaria
 * MassAssignmentException.
 */
function usuarioEnMemoria(string $username = 'prueba'): UsuarioSistema
{
    $usuario = new UsuarioSistema;
    $usuario->forceFill([
        'id' => 1,
        'username' => $username,
        // Hash::make y no un hash pegado a mano: el login compara con
        // Hash::check() usando el mismo algoritmo.
        'password_hash' => Hash::make('secreto123'),
    ]);

    return $usuario;
}

test('la raiz sin sesion redirige al login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('sin sesion, las paginas internas redirigen al login', function () {
    foreach (['dashboard', 'produccion.index', 'history.index'] as $ruta) {
        $this->get(route($ruta))->assertRedirect(route('login'));
    }
});

test('con sesion, la raiz va al dashboard', function () {
    $this->actingAs(usuarioEnMemoria())
        ->get('/')
        ->assertRedirect(route('dashboard'));
});

test('con sesion, un usuario ya logueado no ve el login', function () {
    // Si 'guest' no estuviera en el GET /login, se veria el formulario de
    // login Again. El destino lo fija redirectUsersTo en bootstrap/app.php.
    $this->actingAs(usuarioEnMemoria())
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});

test('el logout cierra la sesion y vuelve al login', function () {
    $this->actingAs(usuarioEnMemoria())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('el logout sin sesion tambien vuelve al login', function () {
    $this->post(route('logout'))->assertRedirect(route('login'));
});

test('el formulario exige usuario y contraseña y vuelve al login', function () {
    // La validacion corre ANTES de tocar la base, asi que esto no necesita BD.
    $this->from(route('login'))
        ->post(route('login.attempt'), [])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['username', 'password']);
});

test('el login se envia por POST y no por GET', function () {
    // Regresion del error original: el formulario mandaba POST a un /login que
    // solo aceptaba GET y Laravel respondia 405. Con la ruta POST declarada
    // responde 302 al login, no 405.
    $this->post(route('login.attempt'), [])->assertStatus(302);
});

test('la vista del login trae el formulario con csrf y action correcta', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    // action del formulario = ruta POST del login
    expect($html)->toContain('action="'.route('login.attempt').'"');
    expect($html)->toContain('name="_token"');
    expect($html)->toContain('name="username"');
    expect($html)->toContain('type="password"');
    expect($html)->toContain('autocomplete="username"');
    expect($html)->toContain('autocomplete="current-password"');
});
