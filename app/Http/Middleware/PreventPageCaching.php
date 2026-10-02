<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventPageCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // También cubre las redirecciones que Laravel genera al rechazar auth/guest.
        $authenticationPage = collect($request->route()?->gatherMiddleware() ?? [])
            ->contains(fn (string $middleware): bool => in_array(
                explode(':', $middleware, 2)[0], ['auth', 'guest'], true,
            ));

        if (! $authenticationPage) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
