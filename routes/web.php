<?php

use App\Http\Controllers\HistorialController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProduccionController;

/**
 * Rutas públicas: solo el login.
 * El resto del sistema requiere sesión iniciada.
 */
Route::get('/login', [LoginController::class, 'index'])->name('login');

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
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/produccion', [ProduccionController::class, 'index'])->name('produccion.index');
    Route::post('/produccion', [ProduccionController::class, 'store'])->name('produccion.store');
    Route::get('/history', [HistorialController::class, 'index'])->name('history.index');
});
