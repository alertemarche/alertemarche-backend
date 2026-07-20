<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'emails transactionnels via l'API Brevo (ex-Sendinblue).
 */
class BrevoService
{
    protected string $key;
    protected string $senderEmail;
    protected string $senderName;
    protected string $baseUrl;

    public function __construct()
    {
        $this->key = (string) config('services.brevo.key');
        $this->senderEmail = (string) config('services.brevo.sender_email');
        $this->senderName = (string) config('services.brevo.sender_name');
        $this->baseUrl = rtrim((string) config('services.brevo.base_url'), '/');
    }

    /** Envoi d'un email HTML transactionnel. */
    public function send(string $toEmail, ?string $toName, string $subject, string $htmlContent): bool
    {
        if (empty($this->key)) {
            Log::warning('Brevo: clé API absente, email non envoyé.', ['to' => $toEmail]);

            return false;
        }

        try {
            $response = Http::withHeaders([
                'api-key' => $this->key,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])->timeout(30)->post($this->baseUrl.'/smtp/email', [
                'sender' => ['email' => $this->senderEmail, 'name' => $this->senderName],
                'to' => [['email' => $toEmail, 'name' => $toName ?: $toEmail]],
                'subject' => $subject,
                'htmlContent' => $htmlContent,
            ]);

            if ($response->failed()) {
                Log::error('Brevo erreur HTTP', ['status' => $response->status(), 'body' => $response->body()]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Brevo exception', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /** Email de bienvenue à l'inscription. */
    public function sendWelcome(string $email, ?string $name, string $profile): bool
    {
        $html = view('emails.welcome', ['name' => $name, 'profile' => $profile])->render();

        return $this->send($email, $name, 'Bienvenue sur AlerteMarché', $html);
    }

    /** Email d'alerte (appel d'offres ou besoin artisan). */
    public function sendAlert(string $email, ?string $name, string $subject, string $body): bool
    {
        $html = view('emails.alert', ['name' => $name, 'subject' => $subject, 'body' => nl2br(e($body))])->render();

        return $this->send($email, $name, $subject, $html);
    }
}
