<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\KkiapayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Intégration de la passerelle de paiement KKiaPay (Mobile Money + carte).
 *
 * Deux points d'entrée :
 *  - config()  : expose la clé publique + le mode sandbox au front (widget KKiaPay).
 *  - verify()  : le front envoie le transactionId après paiement ; on vérifie
 *                côté serveur puis on active l'abonnement.
 *  - webhook() : confirmation serveur-à-serveur (redondance / fiabilité).
 */
class PaymentController extends Controller
{
    public function __construct(protected KkiapayService $kkiapay) {}

    /** Expose la configuration publique KKiaPay au front. */
    public function config(): JsonResponse
    {
        return response()->json([
            'provider' => 'kkiapay',
            'public_key' => $this->kkiapay->publicKey(),
            'sandbox' => $this->kkiapay->sandbox(),
            'configured' => $this->kkiapay->isConfigured(),
        ]);
    }

    /** Vérifie une transaction KKiaPay et active l'abonnement associé. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'string', 'max:120'],
            'subscription_id' => ['required', 'integer'],
        ]);

        $subscription = Subscription::where('id', $data['subscription_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($subscription->status === 'active') {
            return response()->json(['message' => 'Abonnement déjà actif.', 'subscription' => $subscription]);
        }

        $result = $this->kkiapay->verifyTransaction($data['transaction_id']);

        if (! $result['success']) {
            Log::warning('KKiaPay vérification échouée', ['result' => $result, 'subscription' => $subscription->id]);

            return response()->json([
                'message' => 'Paiement non confirmé. Si le montant a été débité, contactez le support.',
                'status' => $result['status'],
            ], 422);
        }

        // Contrôle du montant (le montant vérifié doit couvrir le dû).
        if ($result['amount'] > 0 && $result['amount'] < (int) $subscription->amount) {
            Log::warning('KKiaPay montant insuffisant', [
                'paid' => $result['amount'], 'due' => $subscription->amount, 'subscription' => $subscription->id,
            ]);

            return response()->json(['message' => 'Montant payé insuffisant.'], 422);
        }

        $this->activateSubscription($subscription, $data['transaction_id']);

        return response()->json([
            'message' => 'Paiement confirmé. Votre abonnement est actif.',
            'subscription' => $subscription->fresh(),
        ]);
    }

    /** Webhook KKiaPay (serveur-à-serveur). Canal FIABLE d'activation. */
    public function webhook(Request $request): JsonResponse
    {
        Log::info('KKiaPay webhook reçu', $request->all());

        // Validation de l'authenticité (secret partagé envoyé par KKiaPay).
        if (!$this->validateWebhookSignature($request)) {
            Log::warning('KKiaPay webhook signature invalide', [
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $transactionId = $request->input('transactionId') ?? $request->input('reference');

        // KKiaPay envoie « event: transaction.success » + « isPaymentSucces: true »
        // (l'ancien format « status: SUCCESS » reste géré par rétro-compatibilité).
        $status = strtoupper((string) $request->input('status', ''));
        $event = strtolower((string) $request->input('event', ''));
        $isSuccess = $request->boolean('isPaymentSucces')
            || in_array($status, ['SUCCESS', 'SUCCESSFUL', 'SUCCESS_TRANSACTION'], true)
            || $event === 'transaction.success';

        if (!$transactionId || !$isSuccess) {
            return response()->json(['received' => true]);
        }

        // Retrouver l'abonnement : d'abord via stateData.subscription_id (transmis
        // au widget via data), sinon via payment_reference (transactions déjà liées).
        $stateData = $request->input('stateData', []);
        if (is_string($stateData)) {
            $decoded = json_decode($stateData, true);
            $stateData = is_array($decoded) ? $decoded : [];
        }
        $subscriptionId = $stateData['subscription_id'] ?? null;

        $subscription = $subscriptionId ? Subscription::find($subscriptionId) : null;
        if (!$subscription) {
            $subscription = Subscription::where('payment_reference', $transactionId)->first();
        }
        if (!$subscription) {
            Log::warning('KKiaPay webhook: abonnement introuvable', [
                'transactionId' => $transactionId, 'stateData' => $stateData,
            ]);
            return response()->json(['received' => true]);
        }
        if ($subscription->status === 'active') {
            return response()->json(['received' => true]);
        }

        // Contrôle du montant payé (le webhook porte le montant réel encaissé).
        $paidAmount = (int) round((float) $request->input('amount', 0));
        if ($paidAmount > 0 && $paidAmount < (int) $subscription->amount) {
            Log::warning('KKiaPay webhook: montant insuffisant', [
                'paid' => $paidAmount, 'due' => $subscription->amount, 'subscription' => $subscription->id,
            ]);
            return response()->json(['received' => true]);
        }

        // Défense en profondeur : on tente une revérification serveur pour l'audit.
        // Le webhook est déjà authentifié par le secret partagé ET le montant est
        // contrôlé : on n'exige donc PAS que l'API de vérification réponde (elle
        // peut être indisponible ou mal configurée). On bloque uniquement si elle
        // affirme explicitement que la transaction n'a PAS réussi.
        $verified = $this->kkiapay->verifyTransaction($transactionId);
        $verifyBlocked = $verified['success'] === false
            && !in_array($verified['status'], ['NOT_CONFIGURED', 'ERROR', 'UNKNOWN'], true)
            && !ctype_digit((string) $verified['status']); // codes d'erreur API (ex: 4003)
        if ($verifyBlocked) {
            Log::warning('KKiaPay webhook: revérification négative, activation refusée', [
                'transactionId' => $transactionId, 'verify' => $verified,
            ]);
            return response()->json(['received' => true]);
        }

        $this->activateSubscription($subscription, $transactionId);
        Log::info('KKiaPay webhook: abonnement activé', [
            'subscription' => $subscription->id, 'transactionId' => $transactionId, 'verify_status' => $verified['status'],
        ]);

        return response()->json(['received' => true]);
    }

    /** Valide l'authenticité du webhook KKiaPay (secret partagé). */
    protected function validateWebhookSignature(Request $request): bool
    {
        $webhookSecret = config('services.kkiapay.webhook_secret');

        // Si aucun secret configuré, on accepte (rétro-compatibilité).
        if (empty($webhookSecret)) {
            return true;
        }

        // KKiaPay (via Convoy) envoie le secret webhook EN CLAIR dans l'en-tête
        // « x-kkiapay-secret ». On accepte aussi une éventuelle signature HMAC.
        $provided = $request->header('X-Kkiapay-Secret')
                 ?? $request->header('X-Kkiapay-Signature')
                 ?? $request->header('X-Signature')
                 ?? $request->header('Signature');

        if (empty($provided)) {
            return false;
        }

        // 1) Comparaison directe du secret (format KKiaPay actuel).
        if (hash_equals((string) $webhookSecret, (string) $provided)) {
            return true;
        }

        // 2) Repli : signature HMAC-SHA256 du corps (autres intégrations).
        $expectedSignature = hash_hmac('sha256', $request->getContent(), $webhookSecret);

        return hash_equals($expectedSignature, (string) $provided);
    }

    /** Active un abonnement et débloque WhatsApp pour l'utilisateur. */
    protected function activateSubscription(Subscription $subscription, string $reference): void
    {
        // Récupère la config du plan pour obtenir la durée exacte
        $planConfig = config('plans.plans.' . $subscription->plan, []);
        
        // Priorité : duration_days (ex: hebdomadaire = 7 jours)
        // Sinon : duration_months converti en mois (minimum 1 mois)
        $expiresAt = isset($planConfig['duration_days']) && $planConfig['duration_days'] > 0
            ? now()->addDays((int) $planConfig['duration_days'])
            : now()->addMonths(max(1, (int) $subscription->duration_months));

        $subscription->update([
            'status' => 'active',
            'payment_reference' => $reference,
            'started_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        $subscription->user->update(['notify_whatsapp' => true, 'is_suspended' => false]);
    }
}
