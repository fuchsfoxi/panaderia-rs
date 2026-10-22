<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProduccionController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Middleware\EnsureUsuarioActivo;
use Illuminate\Support\Facades\Route;

// al entrar solo con el dominio, manda al login
Route::redirect('/', '/login');

// rutas públicas: solo para quien NO ha iniciado sesión
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate']);
});

// rutas protegidas: solo para quien ya inició sesión
Route::middleware(['auth', EnsureUsuarioActivo::class, 'auth.session'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::middleware('can:administrar-sistema')->group(function () {
        Route::redirect('/personal', '/usuarios')->name('personal.index');
        Route::get('/grupos', [GrupoController::class, 'index'])->name('grupos.index');
        Route::get('/grupos/crear', [GrupoController::class, 'create'])->name('grupos.create');
        Route::post('/grupos', [GrupoController::class, 'store'])->name('grupos.store');
        Route::get('/grupos/{grupo}/editar', [GrupoController::class, 'edit'])->name('grupos.edit');
        Route::match(['put', 'patch'], '/grupos/{grupo}', [GrupoController::class, 'update'])->name('grupos.update');
        Route::patch('/grupos/{grupo}/estado', [GrupoController::class, 'estado'])->name('grupos.estado');
        Route::get('/empleados', [EmpleadoController::class, 'index'])->name('empleados.index');
        Route::get('/empleados/crear', [EmpleadoController::class, 'create'])->name('empleados.create');
        Route::post('/empleados', [EmpleadoController::class, 'store'])->name('empleados.store');
        Route::get('/empleados/{empleado}/editar', [EmpleadoController::class, 'edit'])->name('empleados.edit');
        Route::match(['put', 'patch'], '/empleados/{empleado}', [EmpleadoController::class, 'update'])->name('empleados.update');
        Route::patch('/empleados/{empleado}/estado', [EmpleadoController::class, 'estado'])->name('empleados.estado');
        Route::delete('/history/{familia}/{detalle}', [HistorialController::class, 'destroy'])
            ->whereIn('familia', ['pan', 'torta', 'bocadito'])->whereNumber('detalle')->name('history.destroy');
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('/usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::match(['put', 'patch'], '/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'estado'])->name('usuarios.estado');
        Route::get('/usuarios/{usuario}/password', [UsuarioController::class, 'password'])->name('usuarios.password');
        Route::patch('/usuarios/{usuario}/password', [UsuarioController::class, 'cambiarPassword'])->name('usuarios.password.update');
        Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
        Route::get('/productos/crear', [ProductoController::class, 'create'])->name('productos.create');
        Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
        Route::get('/productos/{producto}/editar', [ProductoController::class, 'edit'])->name('productos.edit');
        Route::match(['put', 'patch'], '/productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
        Route::patch('/productos/{producto}/estado', [ProductoController::class, 'estado'])->name('productos.estado');
    });

    Route::middleware('can:acceder-operacion')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/produccion', [ProduccionController::class, 'index'])->name('produccion.index');
        Route::post('/produccion', [ProduccionController::class, 'store'])->name('produccion.store');
        Route::post('/produccion/torta', [ProduccionController::class, 'storeTorta'])->name('produccion.torta.store');
        Route::post('/produccion/bocadito', [ProduccionController::class, 'storeBocadito'])->name('produccion.bocadito.store');
        Route::get('/history', [HistorialController::class, 'index'])->name('history.index');
    });
});
