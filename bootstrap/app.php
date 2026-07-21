<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // L'authentification est token-based (Bearer / Sanctum API tokens stockés en localStorage).
        // EnsureFrontendRequestsAreStateful est retiré car il tente une validation CSRF session-based
        // incompatible avec fetch() sans credentials:include → provoquait "CSRF token mismatch".

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'geolocate' => \App\Http\Middleware\GeolocateUser::class,
            'scraper' => \App\Http\Middleware\EnsureScraperToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
