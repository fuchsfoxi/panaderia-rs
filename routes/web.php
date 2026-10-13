<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProduccionController;
use App\Http\Controllers\ProductoController;
use Illuminate\Support\Facades\Route;

// al entrar solo con el dominio, manda al login
Route::redirect('/', '/login');

// rutas públicas: solo para quien NO ha iniciado sesión
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate']);
});

// rutas protegidas: solo para quien ya inició sesión
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::middleware('can:administrar-sistema')->group(function () {
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
