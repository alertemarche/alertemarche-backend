<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(protected PricingService $pricing) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->subscriptions()->latest()->get());
    }

    /**
     * Souscription — crée un abonnement en attente de paiement.
     *
     * Modèle par durée : une seule offre donnant accès à l'intégralité du
     * service. Le client choisit une formule (mensuel / trimestriel /
     * semestriel / annuel). Le montant et la durée sont déterminés côté
     * serveur (source de vérité) à partir de config/plans.php.
     */
    public function store(Request $request): JsonResponse
    {
        $planKeys = array_keys(config('plans.plans', []));

        $data = $request->validate([
            'plan' => ['required', Rule::in($planKeys)],
            'auto_renew' => ['nullable', 'boolean'],
        ]);

        $plan = config('plans.plans.' . $data['plan']);

        $subscription = Subscription::create([
            'user_id' => $request->user()->id,
            'plan' => $data['plan'],
            'duration_months' => $plan['duration_months'],
            'amount' => $plan['amount'],
            'base_price' => $plan['amount'],
            'country_count' => 1,
            'promo_applied' => $plan['discount'] > 0,
            'status' => 'pending',
            'auto_renew' => $request->boolean('auto_renew', false),
        ]);

        return response()->json([
            'message' => 'Abonnement créé. Procédez au paiement pour l\'activer.',
            'subscription' => $subscription,
            'plan' => array_merge(['key' => $data['plan']], $plan),
        ], 201);
    }

    /** Simulation d'activation (à remplacer par le webhook KKPays). */
    public function activate(Request $request, Subscription $subscription): JsonResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);

        $subscription->update([
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addMonths(max(1, (int) $subscription->duration_months)),
        ]);

        // WhatsApp devient disponible pour un abonné payant
        $request->user()->update(['notify_whatsapp' => true, 'is_suspended' => false]);

        return response()->json(['message' => 'Abonnement activé.', 'subscription' => $subscription]);
    }
}
