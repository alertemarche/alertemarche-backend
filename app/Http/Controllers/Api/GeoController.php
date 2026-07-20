<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeolocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    public function __construct(protected GeolocationService $geo) {}

    /** Détection automatique du pays de l'utilisateur via IP. */
    public function detect(Request $request): JsonResponse
    {
        $code = $this->geo->countryFromIp($request->ip());
        $names = ['BJ' => 'Bénin', 'TG' => 'Togo', 'CI' => "Côte d'Ivoire"];
        $flags = ['BJ' => '🇧🇯', 'TG' => '🇹🇬', 'CI' => '🇨🇮'];

        return response()->json([
            'country_code' => $code,
            'country_name' => $names[$code] ?? $code,
            'flag' => $flags[$code] ?? '',
            'currency' => 'FCFA',
        ]);
    }
}
