<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration KKiaPay (Mobile Money + carte bancaire — Bénin).
 *
 * Flux : le widget KKiaPay (front) encaisse le paiement et renvoie un
 * transactionId. Le back-end vérifie ce transactionId côté serveur via
 * l'API KKiaPay avant d'activer l'abonnement. Aucune confiance n'est
 * accordée au front : seule la vérification serveur fait foi.
 *
 * Les clés marchand sont fournies via .env (KKIAPAY_PUBLIC_KEY,
 * KKIAPAY_PRIVATE_KEY, KKIAPAY_SECRET).
 */
class KkiapayService
{
    protected string $publicKey;
    protected string $privateKey;
    protected string $secret;
    protected bool $sandbox;
    protected string $apiUrl;

    public function __construct()
    {
        $this->publicKey = (string) config('services.kkiapay.public_key');
        $this->privateKey = (string) config('services.kkiapay.private_key');
        $this->secret = (string) config('services.kkiapay.secret');
        $this->sandbox = (bool) config('services.kkiapay.sandbox', true);
        $this->apiUrl = rtrim((string) config('services.kkiapay.api_url', 'https://api.kkiapay.me'), '/');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->privateKey) && ! empty($this->secret) && ! empty($this->publicKey);
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    public function sandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * Vérifie une transaction auprès de KKiaPay.
     * Retourne un tableau normalisé : ['success' => bool, 'status' => string, 'amount' => int, 'raw' => array].
     */
    public function verifyTransaction(string $transactionId): array
    {
        if (! $this->isConfigured()) {
            Log::warning('KKiaPay non configuré — vérification impossible.', ['transactionId' => $transactionId]);

            return ['success' => false, 'status' => 'NOT_CONFIGURED', 'amount' => 0, 'raw' => []];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->publicKey,
                'x-private-key' => $this->privateKey,
                'x-secret-key' => $this->secret,
                'Accept' => 'application/json',
            ])->timeout(30)->post($this->apiUrl.'/api/v1/transactions/status', [
                'transactionId' => $transactionId,
            ]);

            $body = $response->json() ?? [];
            $status = strtoupper((string) ($body['status'] ?? 'UNKNOWN'));

            return [
                'success' => $status === 'SUCCESS',
                'status' => $status,
                'amount' => (int) round((float) ($body['amount'] ?? 0)),
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('KKiaPay exception vérification', ['message' => $e->getMessage(), 'transactionId' => $transactionId]);

            return ['success' => false, 'status' => 'ERROR', 'amount' => 0, 'raw' => []];
        }
    }
}
