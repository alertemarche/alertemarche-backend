<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessArtisanNeedJob;
use App\Models\Alert;
use App\Models\ArtisanNeed;
use App\Models\ScraperLog;
use App\Models\Subscription;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Back-office PRO BENIN SARL — statistiques, monitoring scrapers,
 * gestion des utilisateurs, validation des besoins publiés.
 */
class AdminController extends Controller
{
    /** Tableau de bord global. */
    public function stats(): JsonResponse
    {
        $usersByProfile = User::selectRaw('profile_type, count(*) as total')
            ->groupBy('profile_type')->pluck('total', 'profile_type');

        $usersByCountry = User::selectRaw('primary_country, count(*) as total')
            ->groupBy('primary_country')->pluck('total', 'primary_country');

        return response()->json([
            'users_total' => User::count(),
            'users_by_profile' => $usersByProfile,
            'users_by_country' => $usersByCountry,
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'revenue_active' => (int) Subscription::where('status', 'active')->sum('amount'),
            'tenders_total' => Tender::count(),
            'tenders_today' => Tender::whereDate('collected_at', today())->count(),
            'needs_pending' => ArtisanNeed::where('status', 'pending')->count(),
            'alerts_sent' => Alert::where('status', 'sent')->count(),
        ]);
    }

    /** Monitoring des robots de collecte (santé par robot). */
    public function scrapers(): JsonResponse
    {
        // Dernière exécution par (pays, source).
        $sources = ScraperLog::selectRaw('country, source_name')
            ->groupBy('country', 'source_name')->get();

        $robots = $sources->map(function ($s) {
            $last = ScraperLog::where('country', $s->country)
                ->where('source_name', $s->source_name)
                ->latest('ran_at')->first();

            $failures24h = ScraperLog::where('country', $s->country)
                ->where('source_name', $s->source_name)
                ->where('status', 'failure')
                ->where('ran_at', '>=', now()->subDay())->count();

            // Santé : ok (succès récent < 6h), stale (aucune exécution récente),
            // ou down (dernier statut = échec).
            $ranAt = $last?->ran_at;
            $stale = !$ranAt || $ranAt->lt(now()->subHours(6));
            $health = 'ok';
            if ($last?->status === 'failure') {
                $health = 'down';
            } elseif ($stale) {
                $health = 'stale';
            }

            return [
                'country' => $s->country,
                'source_name' => $s->source_name,
                'last_run' => $ranAt,
                'last_status' => $last?->status,
                'last_items_collected' => $last?->items_collected,
                'last_items_new' => $last?->items_new,
                'last_message' => $last?->message,
                'failures_24h' => $failures24h,
                'health' => $health,
            ];
        })->values();

        return response()->json([
            'robots' => $robots,
            'summary' => [
                'total' => $robots->count(),
                'ok' => $robots->where('health', 'ok')->count(),
                'stale' => $robots->where('health', 'stale')->count(),
                'down' => $robots->where('health', 'down')->count(),
            ],
            'recent_logs' => ScraperLog::latest('ran_at')->limit(50)->get(),
        ]);
    }

    /** Liste paginée des utilisateurs. */
    public function users(Request $request): JsonResponse
    {
        $query = User::query()->latest();
        if ($request->filled('profile_type')) {
            $query->where('profile_type', $request->string('profile_type'));
        }
        if ($request->filled('country')) {
            $query->where('primary_country', $request->string('country'));
        }

        return response()->json($query->paginate(25));
    }

    /** Besoins en attente de validation éditoriale. */
    public function pendingNeeds(): JsonResponse
    {
        return response()->json(ArtisanNeed::where('status', 'pending')->latest()->paginate(25));
    }

    /** Validation d'un besoin → déclenche IA + matching inverse. */
    public function validateNeed(Request $request, ArtisanNeed $need): JsonResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);

        if ($data['approve']) {
            $need->update([
                'status' => 'approved',
                'validated_by' => $request->user()->id,
                'validated_at' => now(),
            ]);
            ProcessArtisanNeedJob::dispatch($need->id)->onQueue('ai');
            $message = 'Besoin approuvé et mis en diffusion.';
        } else {
            $need->update(['status' => 'rejected', 'validated_by' => $request->user()->id, 'validated_at' => now()]);
            $message = 'Besoin rejeté.';
        }

        return response()->json(['message' => $message, 'need' => $need]);
    }
}
