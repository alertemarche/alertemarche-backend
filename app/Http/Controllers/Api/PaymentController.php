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

    /** Webhook KKiaPay (serveur-à-serveur). */
    public function webhook(Request $request): JsonResponse
    {
        Log::info('KKiaPay webhook reçu', $request->all());

        // Validation de la signature webhook pour sécurité maximale
        if (!$this->validateWebhookSignature($request)) {
            Log::warning('KKiaPay webhook signature invalide', [
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $transactionId = $request->input('transactionId') ?? $request->input('reference');
        $status = strtoupper((string) $request->input('status'));

        if ($transactionId && in_array($status, ['SUCCESS', 'SUCCESSFUL', 'SUCCESS_TRANSACTION'], true)) {
            // Revérification serveur pour ne pas faire confiance au payload brut.
            $result = $this->kkiapay->verifyTransaction($transactionId);
            if ($result['success']) {
                $subscription = Subscription::where('payment_reference', $transactionId)->first();
                if ($subscription && $subscription->status !== 'active') {
                    $this->activateSubscription($subscription, $transactionId);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    /** Valide la signature du webhook KKiaPay. */
    protected function validateWebhookSignature(Request $request): bool
    {
        $webhookSecret = config('services.kkiapay.webhook_secret');
        
        // Si aucun secret configuré, on accepte (rétro-compatibilité)
        if (empty($webhookSecret)) {
            return true;
        }

        $signature = $request->header('X-Kkiapay-Signature') 
                  ?? $request->header('X-Signature')
                  ?? $request->header('Signature');

        if (empty($signature)) {
            return false;
        }

        // KKiaPay utilise HMAC-SHA256 du payload JSON
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($expectedSignature, $signature);
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
