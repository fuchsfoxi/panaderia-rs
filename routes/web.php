<?php

use App\Http\Controllers\HistorialController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProduccionController;

Route::get('/login', [LoginController::class, 'index'])->name('login');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/produccion', [ProduccionController::class, 'index'])->name('produccion.index');
Route::get('/history', [HistorialController::class, 'index'])->name('history.index');