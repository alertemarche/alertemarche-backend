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

    /** Monitoring des robots de collecte. */
    public function scrapers(): JsonResponse
    {
        $latest = ScraperLog::selectRaw('country, source_name, max(ran_at) as last_run')
            ->groupBy('country', 'source_name')->get();

        return response()->json([
            'latest' => $latest,
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
