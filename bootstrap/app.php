<?php

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        // API pure : ne jamais rediriger un invité vers une route web "login" inexistante.
        Authenticate::redirectUsing(fn (Request $request) => null);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'geolocate' => \App\Http\Middleware\GeolocateUser::class,
            'scraper' => \App\Http\Middleware\EnsureScraperToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Les routes API doivent toujours répondre en JSON.
        // Sans cette règle, auth:sanctum tente de rediriger vers une route web "login"
        // inexistante, ce qui transforme une absence de jeton en erreur 500.
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
