<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\ArtisanNeed;
use App\Models\Tender;
use App\Models\User;

/**
 * Diffusion d'une alerte à un abonné en respectant :
 *  - l'obligation d'un abonnement actif (plus d'alertes gratuites) ;
 *  - la règle « 1 opportunité matchée = 1 alerte consommée » (WA + Email = 1) ;
 *  - WhatsApp réservé aux abonnés payants actifs.
 */
class AlertDispatcher
{
    public function __construct(
        protected BrevoService $brevo,
        protected WhatsAppService $whatsapp,
    ) {}

    /** Diffuse une alerte de type appel d'offres. */
    public function dispatchTender(User $user, Tender $tender, float $score = 60.0): ?Alert
    {
        if (! $user->canReceiveAlert()) {
            return null;
        }

        $message = $this->formatTenderMessage($tender);

        return $this->deliver($user, 'tender', $tender->id, $tender->title, $message, $score);
    }

    /** Diffuse une alerte de type besoin artisan. */
    public function dispatchNeed(User $user, ArtisanNeed $need, float $score = 60.0): ?Alert
    {
        if (! $user->canReceiveAlert()) {
            return null;
        }

        $message = $this->formatNeedMessage($need);

        return $this->deliver($user, 'artisan_need', $need->id, $need->trade.' — '.$need->locality, $message, $score);
    }

    protected function deliver(User $user, string $type, int $sourceId, string $title, string $message, float $score): Alert
    {
        $isFree = ! $user->hasActiveSubscription();

        $alert = Alert::create([
            'user_id' => $user->id,
            'source_type' => $type,
            'source_id' => $sourceId,
            'title' => $title,
            'message' => $message,
            'relevance_score' => $score,
            'is_free' => $isFree,
            'status' => 'queued',
        ]);

        // E-mail (tous les profils)
        $emailOk = false;
        if ($user->notify_email && $user->email) {
            $emailOk = $this->brevo->sendAlert($user->email, $user->name, 'Nouvelle opportunité — AlerteMarché', $message);
        }

        // WhatsApp (abonnés payants uniquement)
        $waOk = false;
        if ($user->whatsappEnabled() && $user->phone) {
            $waOk = $this->whatsapp->sendText($user->phone, $message);
        }

        $alert->update([
            'sent_email' => $emailOk,
            'sent_whatsapp' => $waOk,
            'sent_at' => now(),
            'status' => ($emailOk || $waOk) ? 'sent' : 'failed',
        ]);

        // Décompte du quota freemium + suspension éventuelle
        if ($isFree) {
            $user->increment('free_alerts_used');
            if ($user->fresh()->freeAlertsRemaining() <= 0) {
                $user->update(['is_suspended' => true]);
                $this->sendFreemiumExhausted($user);
            }
        }

        return $alert;
    }

    protected function sendFreemiumExhausted(User $user): void
    {
        if ($user->email) {
            $body = "Vous avez utilisé vos 5 alertes gratuites. Abonnez-vous pour continuer à recevoir "
                ."vos opportunités par WhatsApp et E-mail, sans limite.";
            $this->brevo->sendAlert($user->email, $user->name, 'Vos alertes gratuites sont épuisées — AlerteMarché', $body);
        }
    }

    /** Format message WhatsApp/Email — Appel d'offres public (cahier des charges 5.2). */
    public function formatTenderMessage(Tender $tender): string
    {
        $pays = ['BJ' => 'Bénin', 'TG' => 'Togo', 'CI' => "Côte d'Ivoire"][$tender->country] ?? $tender->country;

        return "🔔 Nouvelle opportunité — AlerteMarché\n\n"
            ."📌 Objet : {$tender->title}\n"
            ."🏛 Institution : {$tender->institution}\n"
            ."💰 Montant : ".($tender->estimated_amount ?: 'Non communiqué')."\n"
            ."📅 Date limite : ".($tender->deadline?->format('d/m/Y') ?: 'Non communiquée')."\n"
            ."🌍 Pays : {$pays}\n\n"
            .($tender->ai_summary ? "📝 {$tender->ai_summary}\n\n" : '')
            ."👉 Voir sur le site officiel : {$tender->source_url}\n\n"
            ."— Alerte envoyée par AlerteMarche.com";
    }

    /** Format message WhatsApp/Email — Besoin Artisan (cahier des charges 5.3). */
    public function formatNeedMessage(ArtisanNeed $need): string
    {
        $pays = ['BJ' => 'Bénin', 'TG' => 'Togo', 'CI' => "Côte d'Ivoire"][$need->country] ?? $need->country;
        $loc = $need->locality.($need->region ? ", {$need->region}" : '')." — {$pays}";

        return "🔔 Nouvelle opportunité — AlerteMarché\n\n"
            ."🛠 Domaine : {$need->trade}\n"
            ."🏢 Employeur : ".($need->employer_name ?: 'Entrepreneur privé')."\n"
            ."👷 Besoin : ".($need->people_needed ?: 'Non précisé')."\n"
            ."📍 Localité : {$loc}\n"
            ."📅 Date de début souhaitée : ".($need->start_date?->format('d/m/Y') ?: 'À convenir')."\n\n"
            ."📞 Contacter directement : {$need->contact}\n\n"
            ."— Alerte envoyée par AlerteMarche.com";
    }
}
