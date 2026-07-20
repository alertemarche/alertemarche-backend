<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Détection automatique du pays via l'adresse IP.
 * Retourne un code pays couvert (BJ/TG/CI) ; défaut BJ.
 */
class GeolocationService
{
    public function countryFromIp(?string $ip): string
    {
        $supported = (array) config('alertemarche.countries', ['BJ', 'TG', 'CI']);

        if (empty($ip) || in_array($ip, ['127.0.0.1', '::1'], true)) {
            return 'BJ';
        }

        $code = Cache::remember('geoip_'.$ip, now()->addDay(), function () use ($ip) {
            try {
                $res = Http::timeout(5)->get("http://ip-api.com/json/{$ip}", ['fields' => 'countryCode']);

                return $res->ok() ? (string) $res->json('countryCode') : null;
            } catch (\Throwable) {
                return null;
            }
        });

        return in_array($code, $supported, true) ? $code : 'BJ';
    }
}
