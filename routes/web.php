<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProduccionController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas públicas: solo el login.
 * El resto del sistema requiere sesión iniciada.
 *
 * 'guest' hace que un usuario que YA tiene sesión no vea el login: lo manda
 * al dashboard (destino configurado en bootstrap/app.php con redirectUsersTo).
 */
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');

    /**
     * El formulario de login se ENVIA por POST a /login, asi que esta ruta es
     * la que evita el "405 Method Not Allowed".
     *
     * El GET conserva el nombre 'login' a proposito: el middleware 'auth'
     * redirige a route('login') cuando no hay sesión, y si este GET perdiera
     * ese nombre habria que cambiar tambien el destino del middleware. Por
     * eso el POST lleva su propio nombre, 'login.attempt'.
     */
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.attempt');
});

/**
 * Rutas protegidas por autenticación.
 *
 * Estas 3 páginas muestran datos REALES de producción (ver DashboardController,
 * ProduccionController e HistorialController), así que sin sesión activa
 * quedarían expuestos.
 *
 * El guard 'web' usa el provider 'users' de config/auth.php, que apunta a
 * App\Models\UsuarioSistema (tabla usuarios_sistema) — NO al modelo User
 * default de Laravel. Se escribe 'auth:web' explícito para que quede claro
 * cuál es el guard, en vez de depender del valor de 'defaults'.
 *
 * Sin sesión, Laravel redirige a la ruta 'login' (declarada arriba).
 */
Route::middleware('auth:web')->group(function () {
    /**
     * Raíz del sitio: con sesión va al dashboard y sin sesión el middleware
     * 'auth:web' la manda al login. Se declara acá adentro (y no suelta) para
     * que no quede una URL publica por donde se pueda entrar.
     */
    Route::get('/', fn () => redirect()->route('dashboard'))->name('inicio');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/produccion', [ProduccionController::class, 'index'])->name('produccion.index');
    Route::post('/produccion', [ProduccionController::class, 'store'])->name('produccion.store');
    Route::get('/history', [HistorialController::class, 'index'])->name('history.index');

    /**
     * Modulo de pedidos: NO esta implementado todavia. La ruta existe para que
     * el enlace del menu no se rompa y muestre una pagina de aviso, en vez de
     * dejar un href="#" o un 404. Va dentro del grupo 'auth' porque el menu
     * lateral solo se ve con sesion.
     */
    Route::get('/pedidos', fn () => view('pedidos.index'))->name('pedidos.index');

    /**
     * Logout por POST y no por GET: un GET se puede disparar desde un <img>
     * o un enlace externo y cerraria la sesion del usuario sin que el lo pida.
     */
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
