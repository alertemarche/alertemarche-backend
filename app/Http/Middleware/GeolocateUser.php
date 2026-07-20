<?php

namespace App\Http\Middleware;

use App\Services\GeolocationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GeolocateUser
{
    public function __construct(protected GeolocationService $geo) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('country_code', $this->geo->countryFromIp($request->ip()));

        return $next($request);
    }
}
