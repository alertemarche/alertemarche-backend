<?php

namespace App\Jobs;

use App\Models\NewsletterAm;
use App\Services\BrevoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envoi d'une campagne (newsletter / annonce) aux destinataires ciblés,
 * par lots de 50, via l'API Brevo. Met à jour sent_count et passe le statut
 * à « sent » en fin de traitement.
 */
class SendNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public int $newsletterId)
    {
    }

    public function handle(BrevoService $brevo): void
    {
        $nl = NewsletterAm::find($this->newsletterId);
        if (! $nl) {
            Log::warning('SendNewsletterJob: campagne introuvable', ['id' => $this->newsletterId]);

            return;
        }

        if ($nl->status === 'sent') {
            Log::info('SendNewsletterJob: campagne déjà envoyée', ['id' => $nl->id]);

            return;
        }

        $nl->update(['status' => 'sending']);

        $subject = (string) $nl->subject;
        $type = (string) $nl->type;
        // Le corps est saisi en HTML léger (éditeur admin) → rendu tel quel.
        $html = view('emails.newsletter_am', [
            'subject' => $subject,
            'body' => $nl->body,
            'type' => $type,
        ])->render();

        $sent = 0;

        // Parcours par lots de 50 pour limiter la mémoire et suivre la progression.
        $nl->recipientsQuery()
            ->select(['id', 'name', 'email'])
            ->chunkById(50, function ($users) use ($brevo, $subject, $html, $nl, &$sent) {
                foreach ($users as $user) {
                    if (empty($user->email)) {
                        continue;
                    }
                    try {
                        if ($brevo->send($user->email, $user->name, $subject, $html)) {
                            $sent++;
                        }
                    } catch (\Throwable $e) {
                        Log::error('SendNewsletterJob: échec envoi', [
                            'newsletter_id' => $nl->id,
                            'email' => $user->email,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                // Mise à jour incrémentale de la progression.
                $nl->update(['sent_count' => $sent]);
            });

        $nl->update([
            'sent_count' => $sent,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        Log::info('SendNewsletterJob: campagne envoyée', ['id' => $nl->id, 'sent' => $sent]);
    }
}
