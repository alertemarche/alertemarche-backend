<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service WhatsApp Business Platform (Meta).
 * Canal premium — disponible uniquement pour les abonnés payants.
 * Les clés Meta seront renseignées ultérieurement (placeholders dans .env).
 */
class WhatsAppService
{
    protected string $token;
    protected string $phoneId;
    protected string $baseUrl;

    public function __construct()
    {
        $this->token = (string) config('services.whatsapp.token');
        $this->phoneId = (string) config('services.whatsapp.phone_number_id');
        $this->baseUrl = rtrim((string) config('services.whatsapp.base_url'), '/');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->token)
            && ! empty($this->phoneId)
            && ! str_starts_with($this->token, 'VOTRE_');
    }

    /** Envoi d'un message texte WhatsApp. */
    public function sendText(string $toPhone, string $message): bool
    {
        if (! $this->isConfigured()) {
            Log::info('WhatsApp non configuré — message simulé.', ['to' => $toPhone]);

            return false;
        }

        try {
            $response = Http::withToken($this->token)->timeout(30)
                ->post($this->baseUrl.'/'.$this->phoneId.'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->normalize($toPhone),
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            if ($response->failed()) {
                Log::error('WhatsApp erreur HTTP', ['status' => $response->status(), 'body' => $response->body()]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsApp exception', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Envoi d'un message via un modèle (template) approuvé par Meta.
     * Obligatoire pour les messages « à froid » (alertes) hors fenêtre de 24 h.
     *
     * @param  string[]  $bodyParams  Paramètres positionnels du corps ({{1}}, {{2}}, ...)
     */
    public function sendTemplate(string $toPhone, string $templateName, string $langCode, array $bodyParams = []): bool
    {
        if (! $this->isConfigured()) {
            Log::info('WhatsApp non configuré — modèle simulé.', ['to' => $toPhone, 'template' => $templateName]);

            return false;
        }

        $components = [];
        if (! empty($bodyParams)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    fn ($p) => ['type' => 'text', 'text' => $this->sanitizeParam((string) $p)],
                    $bodyParams
                ),
            ];
        }

        try {
            $response = Http::withToken($this->token)->timeout(30)
                ->post($this->baseUrl.'/'.$this->phoneId.'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->normalize($toPhone),
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => $langCode],
                        'components' => $components,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('WhatsApp (template) erreur HTTP', [
                    'template' => $templateName,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsApp (template) exception', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /** Les paramètres de template interdisent les retours à la ligne et espaces multiples. */
    protected function sanitizeParam(string $value): string
    {
        $value = preg_replace('/[\r\n\t]+/', ' ', $value);
        $value = preg_replace('/ {2,}/', ' ', $value);

        return trim($value);
    }

    protected function normalize(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
