<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * Sin esto el middleware 'guest' manda al usuario que ya tiene sesión
         * a '/home', ruta que este proyecto no tiene (y por eso terminaba en
         * un 404). Se fija el dashboard como destino unico.
         */
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        /**
         * Destino del middleware 'auth' cuando no hay sesión. route('login')
         * es el GET que se declara en routes/web.php.
         */
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
