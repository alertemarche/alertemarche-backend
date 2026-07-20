<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PricingController extends Controller
{
    public function __construct(protected PricingService $pricing) {}

    /** Devis dynamique — configurateur tarifs multi-pays. */
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profile_type' => ['required', Rule::in(['prestataire', 'artisan', 'admin_public', 'ong'])],
            'country_count' => ['required', 'integer', 'min:1', 'max:3'],
            'promo' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->pricing->quote(
            $data['profile_type'],
            (int) $data['country_count'],
            $request->boolean('promo', true),
        ));
    }

    /** Grille complète des 4 profils. */
    public function grid(): JsonResponse
    {
        $profiles = ['prestataire', 'artisan', 'admin_public', 'ong'];
        $grid = [];
        foreach ($profiles as $p) {
            $grid[$p] = [
                1 => $this->pricing->quote($p, 1),
                2 => $this->pricing->quote($p, 2),
                3 => $this->pricing->quote($p, 3),
            ];
        }

        return response()->json($grid);
    }
}
