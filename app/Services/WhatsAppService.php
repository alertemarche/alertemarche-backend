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

    protected function normalize(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
