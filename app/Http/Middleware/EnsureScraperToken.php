<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentifie les requêtes des robots de collecte via un jeton partagé.
 */
class EnsureScraperToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->header('X-Scraper-Token');

        if (! $token || ! hash_equals((string) config('services.scrapers.token'), $token)) {
            return response()->json(['message' => 'Jeton scraper invalide.'], 401);
        }

        return $next($request);
    }
}
