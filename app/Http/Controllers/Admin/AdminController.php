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
use Illuminate\Support\Str;

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
     * Calendrier d'activité mensuel : pour chaque jour du mois demandé,
     * nombre d'appels d'offres collectés, d'alertes e-mail envoyées et
     * d'autres nouveautés (items_new des robots). Ventilé par pays.
     * Paramètres : month (YYYY-MM, défaut mois courant), country (optionnel).
     */
    public function activityCalendar(Request $request): JsonResponse
    {
        // Mois demandé (format YYYY-MM), sinon mois courant.
        $monthParam = (string) $request->query('month', '');
        try {
            $start = $monthParam !== ''
                ? \Carbon\Carbon::createFromFormat('Y-m-d', $monthParam . '-01')->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
        }
        $end = (clone $start)->endOfMonth();
        $country = $request->query('country'); // 'BJ' | 'CI' | 'TG' | null

        // Appels d'offres collectés par jour (et par pays).
        $tenders = Tender::query()
            ->whereBetween('collected_at', [$start, $end])
            ->when($country, fn ($q) => $q->where('country', $country))
            ->selectRaw("to_char(collected_at, 'YYYY-MM-DD') as day, count(*) as total")
            ->groupBy('day')->pluck('total', 'day');

        // Alertes e-mail envoyées par jour (jointure users pour le pays).
        $alerts = Alert::query()
            ->join('users', 'users.id', '=', 'alerts.user_id')
            ->where('alerts.status', 'sent')
            ->where('alerts.sent_email', true)
            ->whereBetween('alerts.sent_at', [$start, $end])
            ->when($country, fn ($q) => $q->where('users.primary_country', $country))
            ->selectRaw("to_char(alerts.sent_at, 'YYYY-MM-DD') as day, count(*) as total")
            ->groupBy('day')->pluck('total', 'day');

        // Autres nouveautés : items_new agrégés des robots par jour (et par pays).
        $others = ScraperLog::query()
            ->whereBetween('ran_at', [$start, $end])
            ->when($country, fn ($q) => $q->where('country', $country))
            ->selectRaw("to_char(ran_at, 'YYYY-MM-DD') as day, coalesce(sum(items_new),0) as total")
            ->groupBy('day')->pluck('total', 'day');

        // Construit une entrée par jour du mois.
        $days = [];
        $totTenders = 0; $totAlerts = 0; $totOthers = 0;
        for ($d = (clone $start); $d->lte($end); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $t = (int) ($tenders[$key] ?? 0);
            $a = (int) ($alerts[$key] ?? 0);
            $o = (int) ($others[$key] ?? 0);
            $totTenders += $t; $totAlerts += $a; $totOthers += $o;
            $days[] = [
                'date'             => $key,
                'tenders_collected' => $t,
                'alerts_sent'      => $a,
                'items_new'        => $o,
            ];
        }

        return response()->json([
            'month'   => $start->format('Y-m'),
            'country' => $country ?: 'all',
            'days'    => $days,
            'totals'  => [
                'tenders_collected' => $totTenders,
                'alerts_sent'       => $totAlerts,
                'items_new'         => $totOthers,
            ],
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
            ->withCount(['alerts as alerts_count' => fn ($q) => $q->where('status', 'sent')])
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

    // =========================================================================
    // NOUVELLES MÉTHODES — Gestion avancée du back-office
    // =========================================================================

    /**
     * Connexion admin autonome — authentifie avec ADMIN_USER / ADMIN_PASSWORD
     * (variables d'environnement) et renvoie un token Sanctum admin.
     * Route publique : POST /api/admin/login (pas de middleware auth).
     */
    public function adminLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $expectedUser = env('ADMIN_USER', 'admin');
        $expectedPass = env('ADMIN_PASSWORD', 'Internet123');

        if ($data['username'] !== $expectedUser || $data['password'] !== $expectedPass) {
            return response()->json(['message' => 'Identifiants administrateur incorrects.'], 401);
        }

        $adminEmail = env('ADMIN_EMAIL', 'admin@alertemarche.com');

        $user = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name'             => 'Administrateur',
                'organization'     => 'AlerteMarché',
                'password'         => bcrypt($expectedPass),
                'is_admin'         => true,
                'email_verified_at'=> now(),
                'primary_country'  => 'BJ',
                'profile_type'     => 'prestataire',
                'phone'            => '+229 00000000',
            ]
        );

        if (!$user->is_admin) {
            $user->update(['is_admin' => true]);
        }

        // Invalider les anciens tokens admin et en créer un nouveau
        $user->tokens()->where('name', 'admin-session')->delete();
        $token = $user->createToken('admin-session')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_admin' => true],
        ]);
    }

    /**
     * Récupère les détails complets d'un utilisateur avec ses abonnements,
     * alertes récentes et statistiques d'activité.
     */
    public function showUser(User $user): JsonResponse
    {
        $user->load([
            'subscriptions' => fn ($q) => $q->latest()->limit(10),
            'alerts' => fn ($q) => $q->latest()->limit(20),
        ]);

        $alertsStats = [
            'total' => $user->alerts()->count(),
            'sent' => $user->alerts()->where('status', 'sent')->count(),
            'pending' => $user->alerts()->where('status', 'pending')->count(),
            'failed' => $user->alerts()->where('status', 'failed')->count(),
        ];

        $active = $user->activeSubscription();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'organization' => $user->organization,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_type' => $user->profile_type,
                'primary_country' => $user->primary_country,
                'sectors' => $user->sectors,
                'keywords' => $user->keywords,
                'is_admin' => (bool) $user->is_admin,
                'is_suspended' => (bool) $user->is_suspended,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'subscription' => $active ? [
                'plan' => $active->plan,
                'status' => $active->status,
                'amount' => (int) $active->amount,
                'started_at' => $active->started_at,
                'expires_at' => $active->expires_at,
                'payment_reference' => $active->payment_reference,
            ] : null,
            'subscriptions_history' => $user->subscriptions,
            'alerts_stats' => $alertsStats,
            'recent_alerts' => $user->alerts,
        ]);
    }

    /**
     * Modifie les informations d'un utilisateur (admin peut modifier tous les champs).
     */
    public function updateUser(Request $request, User $user): JsonResponse
    {
        if ($user->is_admin && !$request->user()->is_admin) {
            return response()->json(['message' => 'Seul un admin peut modifier un compte admin.'], 403);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'profile_type' => ['nullable', 'string'],
            'primary_country' => ['nullable', 'string', 'max:5'],
            'sectors' => ['nullable', 'array'],
            'keywords' => ['nullable', 'array'],
        ]);

        $user->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json([
            'message' => 'Utilisateur modifié avec succès.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Supprime définitivement un utilisateur (non-admin uniquement).
     * Annule ses abonnements actifs et révoque ses tokens.
     */
    public function deleteUser(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Impossible de supprimer votre propre compte.'], 403);
        }
        if ($user->is_admin) {
            return response()->json(['message' => 'Impossible de supprimer un compte administrateur.'], 403);
        }
        $user->tokens()->delete();
        $user->subscriptions()->update(['status' => 'cancelled']);
        $user->delete();
        return response()->json(['message' => 'Utilisateur supprimé avec succès.']);
    }

    /**
     * Bascule l'état de suspension d'un utilisateur.
     * Un utilisateur suspendu ne peut plus se connecter.
     */
    public function toggleSuspend(Request $request, User $user): JsonResponse
    {
        // On interdit uniquement la SUSPENSION d'un administrateur.
        // La RÉACTIVATION d'un admin (déjà suspendu par erreur) reste toujours possible,
        // sinon un compte admin suspendu resterait bloqué à jamais.
        if ($user->is_admin && !$user->is_suspended) {
            return response()->json(['message' => 'Impossible de suspendre un administrateur.'], 403);
        }
        $user->update(['is_suspended' => !$user->is_suspended]);
        return response()->json([
            'message'      => $user->is_suspended ? 'Utilisateur suspendu.' : 'Utilisateur réactivé.',
            'is_suspended' => (bool) $user->is_suspended,
        ]);
    }

    /**
     * Crée un utilisateur manuellement (sans passer par l'inscription normale).
     * Peut également créer un abonnement actif immédiatement sans paiement.
     */
    public function createUserManual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'organization'    => ['nullable', 'string', 'max:255'],
            'email'           => ['required', 'email', 'unique:users,email'],
            'phone'           => ['required', 'string', 'max:30'],
            'profile_type'    => ['required', 'string'],
            'primary_country' => ['required', 'string', 'max:5'],
            'plan'            => ['nullable', 'string'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:36'],
        ]);

        $tempPassword = Str::random(10);

        $user = User::create([
            'name'             => $data['name'],
            'organization'     => $data['organization'] ?? $data['name'],
            'email'            => $data['email'],
            'phone'            => $data['phone'],
            'profile_type'     => $data['profile_type'],
            'primary_country'  => $data['primary_country'],
            'password'         => bcrypt($tempPassword),
            'email_verified_at'=> now(),
        ]);

        $sub = null;
        if (!empty($data['plan'])) {
            $planAmounts = collect(config('plans.plans', []))
                ->map(fn ($p) => (int) ($p['amount'] ?? 0))->all();
            $duration = $data['duration_months'] ?? match ($data['plan']) {
                'mensuel'     => 1,
                'trimestriel' => 3,
                'semestriel'  => 6,
                default       => 12,
            };

            $sub = Subscription::create([
                'user_id'           => $user->id,
                'plan'              => $data['plan'],
                'duration_months'   => $duration,
                'amount'            => $planAmounts[$data['plan']] ?? 0,
                'status'            => 'active',
                'started_at'        => now(),
                'expires_at'        => now()->addMonths($duration),
                'payment_reference' => 'ADMIN-MANUAL-' . strtoupper(Str::random(6)),
            ]);
        }

        return response()->json([
            'message'      => 'Utilisateur créé avec succès.',
            'user'         => ['id' => $user->id, 'email' => $user->email, 'temp_password' => $tempPassword],
            'subscription' => $sub,
        ], 201);
    }

    /**
     * Accorde un abonnement actif à un utilisateur existant sans paiement.
     * Annule l'éventuel abonnement actif précédent.
     */
    public function grantSubscription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'         => ['required', 'integer', 'exists:users,id'],
            'plan'            => ['required', 'string'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:36'],
            'amount'          => ['nullable', 'integer', 'min:0'],
        ]);

        $planAmounts = collect(config('plans.plans', []))
            ->map(fn ($p) => (int) ($p['amount'] ?? 0))->all();
        $amount = $data['amount'] ?? ($planAmounts[$data['plan']] ?? 0);

        Subscription::where('user_id', $data['user_id'])->where('status', 'active')->update(['status' => 'cancelled']);

        $sub = Subscription::create([
            'user_id'           => $data['user_id'],
            'plan'              => $data['plan'],
            'duration_months'   => $data['duration_months'],
            'amount'            => $amount,
            'status'            => 'active',
            'started_at'        => now(),
            'expires_at'        => now()->addMonths($data['duration_months']),
            'payment_reference' => 'ADMIN-GRANT-' . strtoupper(Str::random(6)),
        ]);

        return response()->json(['message' => 'Abonnement accordé avec succès.', 'subscription' => $sub], 201);
    }

    /**
     * Liste de tous les paiements / transactions (abonnements avec référence de paiement).
     * Distingue les paiements réels (KKiaPay) des créations manuelles par l'admin.
     */
    public function payments(Request $request): JsonResponse
    {
        $query = Subscription::query()->with('user')->latest();

        $map = function (Subscription $s) {
            $ref = $s->payment_reference ?? '';
            $isManual = str_starts_with($ref, 'ADMIN');
            return [
                'id'                => $s->id,
                'user_id'           => $s->user_id,
                'organization'      => $s->user?->organization,
                'name'              => $s->user?->name,
                'email'             => $s->user?->email,
                'phone'             => $s->user?->phone,
                'plan'              => $s->plan,
                'duration_months'   => $s->duration_months,
                'amount'            => (int) $s->amount,
                'status'            => $s->status,
                'payment_reference' => $ref,
                'is_manual'         => $isManual,
                'started_at'        => $s->started_at,
                'created_at'        => $s->created_at,
            ];
        };

        $totalReal = (int) Subscription::whereNotNull('payment_reference')
            ->where('payment_reference', 'not like', 'ADMIN%')
            ->sum('amount');

        if ($request->boolean('all')) {
            return response()->json([
                'data'       => $query->get()->map($map)->values(),
                'total_real' => $totalReal,
                'total_all'  => (int) Subscription::sum('amount'),
            ]);
        }

        $page = $query->paginate(50);
        return response()->json([
            'data'         => collect($page->items())->map($map)->values(),
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'total'        => $page->total(),
            'total_real'   => $totalReal,
            'total_all'    => (int) Subscription::sum('amount'),
        ]);
    }
}
