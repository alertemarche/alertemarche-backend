<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StatsController
{
    /**
     * Statistiques publiques d'AlerteMarché (mises en cache 15 min).
     */
    public function public(): JsonResponse
    {
        $stats = Cache::remember('public_stats', 900, function () {
            $row = DB::selectOne("
                SELECT
                    (SELECT COUNT(*) FROM tenders WHERE deadline >= NOW() OR deadline IS NULL)      AS marches_actifs,
                    (SELECT COUNT(*) FROM tenders)                                                  AS total_marches,
                    (SELECT COUNT(*) FROM users WHERE is_admin = false AND email_verified_at IS NOT NULL) AS utilisateurs_inscrits,
                    (SELECT COUNT(DISTINCT country) FROM tenders)                                   AS pays_couverts
            ");

            return [
                'marches_actifs'      => (int) $row->marches_actifs,
                'total_marches'       => (int) $row->total_marches,
                'utilisateurs_inscrits' => (int) $row->utilisateurs_inscrits,
                'pays_couverts'       => (int) $row->pays_couverts,
                'pays'                => ['BJ', 'TG', 'CI', 'SN', 'BF'],
                'updated_at'          => now()->toIso8601String(),
            ];
        });

        return response()->json($stats)->header('Access-Control-Allow-Origin', '*');
    }
}
