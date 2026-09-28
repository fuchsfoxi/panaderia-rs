<?php

namespace App\Providers;

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
        /**
         * Idioma de la interfaz: español.
         *
         * Se fija acá y no solo en el .env a propósito: el .env es local de
         * cada máquina y si queda en APP_LOCALE=en los mensajes de validación
         * del formulario de producción se leen en inglés frente a los usuarios
         * que van a probar el sistema. Los archivos de traducción están en
         * lang/es/.
         *
         * El idioma de respaldo también va en español: si una regla no está
         * traducida, sale en español y no en inglés.
         */
        config([
            'app.locale' => 'es',
            'app.fallback_locale' => 'es',
        ]);
    }
}
