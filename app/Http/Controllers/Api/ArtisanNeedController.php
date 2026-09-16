<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtisanNeed;
use App\Services\KkiapayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArtisanNeedController extends Controller
{
    /** Liste publique des besoins approuvés — les annonces PREMIUM d'abord. */
    public function index(Request $request): JsonResponse
    {
        $query = ArtisanNeed::query()
            ->where('status', 'approved')
            ->orderByRaw('is_premium DESC, created_at DESC');

        if ($request->filled('country')) {
            $query->where('country', $request->string('country'));
        }
        if ($request->filled('trade')) {
            $query->where('trade', 'ilike', '%'.$request->string('trade').'%');
        }
        if ($request->filled('is_premium')) {
            $query->where('is_premium', filter_var($request->input('is_premium'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(1, min($perPage, 50));

        return response()->json($query->paginate($perPage));
    }

    /** Publication d'un besoin (entreprises, admin, ONG) — validation éditoriale requise. */
    public function store(Request $request, KkiapayService $kkiapay): JsonResponse
    {
        $data = $request->validate([
            'trade' => ['required', 'string', 'max:255'],
            'employer_name' => ['nullable', 'string', 'max:255'],
            'people_needed' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'locality' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['required', Rule::in(['BJ', 'TG', 'CI', 'SN', 'BF'])],
            'estimated_budget' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'contact' => ['required', 'string', 'max:255'],
            'is_premium' => ['nullable', 'boolean'],
            'payment_ref' => ['nullable', 'string', 'max:255'],
        ]);

        $wantsPremium = (bool) ($data['is_premium'] ?? false);
        $paymentRef = $data['payment_ref'] ?? null;

        // Valeurs par défaut : annonce GRATUITE (15 jours de visibilité).
        $isPremium = false;
        $paidAt = null;
        $expiresAt = now()->addDays(15);
        $warning = null;

        // Tentative d'activation PREMIUM : vérification serveur du paiement KKiaPay.
        if ($wantsPremium && $paymentRef) {
            $result = $kkiapay->verifyTransaction($paymentRef);
            if ($result['success']) {
                $isPremium = true;
                $paidAt = now();
                $expiresAt = now()->addDays(30);
            } else {
                // Paiement non confirmé : on crée quand même l'annonce en GRATUIT.
                $warning = "Le paiement PREMIUM n'a pas pu être confirmé. Votre annonce a été enregistrée en formule gratuite (15 jours). Contactez-nous si vous avez été débité.";
            }
        } elseif ($wantsPremium && ! $paymentRef) {
            $warning = "Aucune preuve de paiement fournie. Votre annonce a été enregistrée en formule gratuite (15 jours).";
        }

        unset($data['is_premium'], $data['payment_ref']);

        $need = ArtisanNeed::create([
            ...$data,
            'publisher_id' => $request->user()?->id,
            'status' => 'pending', // en attente de validation éditoriale
            'is_premium' => $isPremium,
            'paid_at' => $paidAt,
            'expires_at' => $expiresAt,
        ]);

        $message = $isPremium
            ? 'Annonce PREMIUM publiée. Elle sera diffusée en priorité après validation par notre équipe.'
            : 'Besoin publié. Il sera diffusé après validation par notre équipe.';

        $payload = ['message' => $message, 'need' => $need];
        if ($warning) {
            $payload['warning'] = $warning;
        }

        return response()->json($payload, 201);
    }

    /** Vérification a posteriori du paiement PREMIUM (widget KKiaPay). */
    public function verifyPremium(Request $request, ArtisanNeed $need, KkiapayService $kkiapay): JsonResponse
    {
        $request->validate([
            'transaction_id' => ['required', 'string', 'max:255'],
        ]);

        abort_unless($need->publisher_id === $request->user()?->id, 403);

        $result = $kkiapay->verifyTransaction((string) $request->input('transaction_id'));

        if (! $result['success']) {
            return response()->json([
                'message' => "Le paiement n'a pas pu être confirmé.",
                'status' => $result['status'],
            ], 422);
        }

        $need->update([
            'is_premium' => true,
            'paid_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'message' => 'Paiement confirmé. Votre annonce est désormais PREMIUM (30 jours).',
            'need' => $need->fresh(),
        ]);
    }

    /** Suivi : nombre d'artisans alertés pour un besoin publié. */
    public function responses(Request $request, ArtisanNeed $need): JsonResponse
    {
        abort_unless($need->publisher_id === $request->user()?->id, 403);

        $count = \App\Models\Alert::where('source_type', 'artisan_need')
            ->where('source_id', $need->id)->count();

        return response()->json(['need_id' => $need->id, 'artisans_alerted' => $count]);
    }
}
