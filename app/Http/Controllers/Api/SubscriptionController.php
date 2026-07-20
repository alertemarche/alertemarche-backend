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

    /** Souscription — crée un abonnement en attente de paiement. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profile_type' => ['required', Rule::in(['prestataire', 'artisan', 'admin_public', 'ong'])],
            'countries' => ['required', 'array', 'min:1', 'max:3'],
            'countries.*' => [Rule::in(['BJ', 'TG', 'CI'])],
            'auto_renew' => ['nullable', 'boolean'],
        ]);

        $countries = array_values(array_unique($data['countries']));
        $count = count($countries);
        $quote = $this->pricing->quote($data['profile_type'], $count, true);

        $subscription = Subscription::create([
            'user_id' => $request->user()->id,
            'profile_type' => $data['profile_type'],
            'countries' => $countries,
            'country_count' => $count,
            'base_price' => $quote['base_price'],
            'amount' => $quote['promo_total'],
            'promo_applied' => true,
            'status' => 'pending',
            'auto_renew' => $request->boolean('auto_renew', false),
        ]);

        return response()->json([
            'message' => 'Abonnement créé. Procédez au paiement pour l\'activer.',
            'subscription' => $subscription,
            'quote' => $quote,
        ], 201);
    }

    /** Simulation d'activation (à remplacer par le webhook KKPays). */
    public function activate(Request $request, Subscription $subscription): JsonResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);

        $subscription->update([
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        // WhatsApp devient disponible pour un abonné payant
        $request->user()->update(['notify_whatsapp' => true, 'is_suspended' => false]);

        return response()->json(['message' => 'Abonnement activé.', 'subscription' => $subscription]);
    }
}
