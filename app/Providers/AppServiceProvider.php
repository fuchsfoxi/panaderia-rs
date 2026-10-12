<?php

namespace App\Providers;

use App\Models\Usuario;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('acceder-operacion', fn (Usuario $usuario): bool => $usuario->puedeAccederOperacion());
        Gate::define('administrar-sistema', fn (Usuario $usuario): bool => $usuario->esAdministrador());
    }
}
