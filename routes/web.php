<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProduccionController;
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
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/produccion', [ProduccionController::class, 'index'])->name('produccion.index');
    Route::post('/produccion', [ProduccionController::class, 'store'])->name('produccion.store');
    Route::get('/history', [HistorialController::class, 'index'])->name('history.index');
});
