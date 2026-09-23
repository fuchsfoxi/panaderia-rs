<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProduccionController;

Route::get('/login', [LoginController::class, 'index'])->name('login');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/produccion', [ProduccionController::class, 'index'])->name('produccion.index');
Route::post('/produccion', [ProduccionController::class, 'store'])->name('produccion.store');