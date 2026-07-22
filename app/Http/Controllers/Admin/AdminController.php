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

    /**
     * Statistiques d'alertes pour estimer la facture (WhatsApp + Email).
     * Renvoie les totaux par canal, la ventilation par pays et par jour.
     * Paramètre : days (période du détail journalier, défaut 30).
     */
    public function alertsStats(Request $request): JsonResponse
    {
        $days = max(1, min(180, (int) $request->integer('days', 30)));
        $since = now()->subDays($days - 1)->startOfDay();

        // Totaux cumulés par canal (toutes alertes effectivement envoyées).
        $sent = Alert::where('status', 'sent');
        $totals = [
            'total'    => (clone $sent)->count(),
            'email'    => (clone $sent)->where('sent_email', true)->count(),
            'whatsapp' => (clone $sent)->where('sent_whatsapp', true)->count(),
        ];

        // Totaux sur la période demandée.
        $sentPeriod = (clone $sent)->where('sent_at', '>=', $since);
        $totalsPeriod = [
            'total'    => (clone $sentPeriod)->count(),
            'email'    => (clone $sentPeriod)->where('sent_email', true)->count(),
            'whatsapp' => (clone $sentPeriod)->where('sent_whatsapp', true)->count(),
        ];

        // Ventilation par pays (via users.primary_country). Syntaxe FILTER PostgreSQL.
        $byCountry = Alert::query()
            ->join('users', 'users.id', '=', 'alerts.user_id')
            ->where('alerts.status', 'sent')
            ->selectRaw("COALESCE(users.primary_country, 'N/A') as country,
                count(*) as total,
                count(*) filter (where alerts.sent_email) as email,
                count(*) filter (where alerts.sent_whatsapp) as whatsapp")
            ->groupBy('users.primary_country')
            ->orderByDesc('total')
            ->get();

        // Ventilation par jour sur la période.
        $byDay = Alert::query()
            ->where('status', 'sent')
            ->where('sent_at', '>=', $since)
            ->selectRaw("to_char(sent_at, 'YYYY-MM-DD') as day,
                count(*) as total,
                count(*) filter (where sent_email) as email,
                count(*) filter (where sent_whatsapp) as whatsapp")
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->json([
            'totals'        => $totals,
            'totals_period' => $totalsPeriod,
            'period_days'   => $days,
            'by_country'    => $byCountry,
            'by_day'        => $byDay,
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

    /**
     * Liste des utilisateurs enrichie (entreprise, contact, abonnement).
     * Paramètres : profile_type, country, q (recherche), all=1 (liste
     * complète pour export CSV), sinon pagination 25.
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['subscriptions' => fn ($q) => $q->latest()])
            ->withCount('alerts')
            ->latest();

        if ($request->filled('profile_type')) {
            $query->where('profile_type', $request->string('profile_type'));
        }
        if ($request->filled('country')) {
            $query->where('primary_country', $request->string('country'));
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($w) use ($term) {
                $w->where('name', 'ilike', $term)
                    ->orWhere('organization', 'ilike', $term)
                    ->orWhere('email', 'ilike', $term)
                    ->orWhere('phone', 'ilike', $term);
            });
        }

        $map = function (User $u) {
            $active = $u->activeSubscription();

            return [
                'id' => $u->id,
                'name' => $u->name,
                'organization' => $u->organization,
                'email' => $u->email,
                'phone' => $u->phone,
                'profile_type' => $u->profile_type,
                'primary_country' => $u->primary_country,
                'sectors' => $u->sectors,
                'keywords' => $u->keywords,
                'is_admin' => (bool) $u->is_admin,
                'is_suspended' => (bool) $u->is_suspended,
                'email_verified' => $u->email_verified_at !== null,
                'alerts_count' => $u->alerts_count,
                'subscription_status' => $active ? 'active' : ($u->subscriptions->isNotEmpty() ? $u->subscriptions->first()->status : 'aucun'),
                'subscription_plan' => $active?->plan,
                'subscription_amount' => $active ? (int) $active->amount : 0,
                'subscription_expires_at' => $active?->expires_at,
                'created_at' => $u->created_at,
            ];
        };

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()->map($map)->values()]);
        }

        $page = $query->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map($map)->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    /**
     * Liste des abonnements avec entreprise/contact du souscripteur,
     * formule, montant, statut et échéances. Synthèse du chiffre
     * d'affaires. Paramètres : status, plan, all=1 (export CSV).
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $query = Subscription::query()->with('user')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('plan')) {
            $query->where('plan', $request->string('plan'));
        }

        $map = function (Subscription $s) {
            return [
                'id' => $s->id,
                'user_id' => $s->user_id,
                'name' => $s->user?->name,
                'organization' => $s->user?->organization,
                'email' => $s->user?->email,
                'phone' => $s->user?->phone,
                'profile_type' => $s->profile_type ?? $s->user?->profile_type,
                'plan' => $s->plan,
                'duration_months' => $s->duration_months,
                'amount' => (int) $s->amount,
                'status' => $s->status,
                'started_at' => $s->started_at,
                'expires_at' => $s->expires_at,
                'payment_reference' => $s->payment_reference,
                'created_at' => $s->created_at,
            ];
        };

        // Synthèse (indépendante des filtres de liste).
        $revenueByPlan = Subscription::where('status', 'active')
            ->selectRaw('plan, count(*) as total, sum(amount) as revenue')
            ->groupBy('plan')->get();

        $summary = [
            'total_subscriptions' => Subscription::count(),
            'active' => Subscription::where('status', 'active')->count(),
            'pending' => Subscription::where('status', 'pending')->count(),
            'expired' => Subscription::whereIn('status', ['expired', 'cancelled'])->count(),
            'revenue_active' => (int) Subscription::where('status', 'active')->sum('amount'),
            'revenue_total' => (int) Subscription::sum('amount'),
            'by_plan' => $revenueByPlan,
        ];

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()->map($map)->values(), 'summary' => $summary]);
        }

        $page = $query->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map($map)->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'summary' => $summary,
        ]);
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
