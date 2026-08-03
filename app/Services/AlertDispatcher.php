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

        $waParams = [
            $user->name ?: 'cher abonné',
            $tender->title,
            $tender->institution ?: 'Non communiqué',
            $tender->deadline?->format('d/m/Y') ?: 'Non communiquée',
        ];

        return $this->deliver($user, 'tender', $tender->id, $tender->title, $message, $score, $waParams);
    }

    /** Diffuse une alerte de type besoin artisan. */
    public function dispatchNeed(User $user, ArtisanNeed $need, float $score = 60.0): ?Alert
    {
        if (! $user->canReceiveAlert()) {
            return null;
        }

        $message = $this->formatNeedMessage($need);

        $waParams = [
            $user->name ?: 'cher abonné',
            $need->trade.($need->locality ? ' — '.$need->locality : ''),
            $need->employer_name ?: 'Entrepreneur privé',
            $need->start_date?->format('d/m/Y') ?: 'À convenir',
        ];

        return $this->deliver($user, 'artisan_need', $need->id, $need->trade.' — '.$need->locality, $message, $score, $waParams);
    }

    /**
     * @param  string[]|null  $waParams  Paramètres positionnels du modèle WhatsApp (alerte à froid).
     */
    protected function deliver(User $user, string $type, int $sourceId, string $title, string $message, float $score, ?array $waParams = null): Alert
    {
        $isFree = ! $user->hasActiveSubscription();

        // Contenu de l'e-mail :
        //  - Abonné payant -> détails complets de l'opportunité.
        //  - Non-abonné    -> UNE alerte « teaser » (sans détails) invitant à s'abonner
        //                     pour accéder aux marchés de son domaine.
        $emailBody = $isFree ? $this->teaserMessage($user) : $message;
        $emailSubject = $isFree
            ? '🔔 De nouveaux marchés dans votre domaine — AlerteMarché'
            : 'Nouvelle opportunité — AlerteMarché';

        $alert = Alert::create([
            'user_id' => $user->id,
            'source_type' => $type,
            'source_id' => $sourceId,
            'title' => $title,
            'message' => $emailBody,
            'relevance_score' => $score,
            'is_free' => $isFree,
            'status' => 'queued',
        ]);

        // E-mail (tous les profils)
        $emailOk = false;
        if ($user->notify_email && $user->email) {
            $emailOk = $this->brevo->sendAlert($user->email, $user->name, $emailSubject, $emailBody);
        }

        // WhatsApp désactivé sur AlerteMarché : les alertes sont désormais
        // envoyées uniquement par e-mail (abonnement e-mail).
        $waOk = false;

        $alert->update([
            'sent_email' => $emailOk,
            'sent_whatsapp' => $waOk,
            'sent_at' => now(),
            'status' => $emailOk ? 'sent' : 'failed',
        ]);

        // Non-abonné : après l'unique alerte teaser, on suspend les envois.
        // Le teaser contient déjà l'appel à s'abonner : inutile d'envoyer en plus
        // un e-mail « alertes épuisées ».
        if ($isFree) {
            $user->increment('free_alerts_used');
            if ($user->fresh()->freeAlertsRemaining() <= 0) {
                $user->update(['is_suspended' => true]);
            }
        }

        return $alert;
    }

    /**
     * Message « teaser » envoyé UNE seule fois à un non-abonné : il l'informe que des
     * marchés correspondent à son domaine, sans en révéler les détails, et l'invite
     * à s'abonner pour y accéder.
     */
    protected function teaserMessage(User $user): string
    {
        $url = 'https://alertemarche.com/tarifs.html';
        $prenom = $user->name ? explode(' ', trim($user->name))[0] : 'Bonjour';

        return "🔔 <strong>De nouveaux marchés correspondent à votre domaine !</strong><br><br>"
            ."{$prenom}, des appels d'offres et opportunités viennent d'être publiés dans votre secteur d'activité sur AlerteMarché.<br><br>"
            ."Pour <strong>consulter les détails</strong> (objet, institution, montant, date limite) et recevoir "
            ."<strong>toutes vos alertes en temps réel</strong> par e-mail, activez votre abonnement :<br><br>"
            .'👉 <a href="'.$url.'" style="display:inline-block;background:#1a7f5a;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:700;">Je m\'abonne pour accéder aux marchés</a><br><br>'
            ."Ne manquez plus aucune opportunité dans votre domaine.<br>"
            .'<p style="margin-top:20px;padding-top:16px;border-top:1px solid #e3ebe7;color:#6b7d77;font-size:13px;">— AlerteMarche.com</p>';
    }

    protected function sendFreemiumExhausted(User $user): void
    {
        if ($user->email) {
            $body = "Vous avez utilisé vos 5 alertes gratuites. Abonnez-vous pour continuer à recevoir "
                ."vos opportunités par e-mail, sans limite.";
            $this->brevo->sendAlert($user->email, $user->name, 'Vos alertes gratuites sont épuisées — AlerteMarché', $body);
        }
    }

    /** Format message WhatsApp/Email — Appel d'offres public (cahier des charges 5.2). */
    public function formatTenderMessage(Tender $tender): string
    {
        $pays = ['BJ' => 'Bénin', 'TG' => 'Togo', 'CI' => "Côte d'Ivoire", 'SN' => 'Sénégal'][$tender->country] ?? $tender->country;

        // Génère du HTML pour l'email (les \n sont convertis en <br> via nl2br, mais on ajoute aussi du HTML pour le lien)
        $message = "🔔 <strong>Nouvelle opportunité — AlerteMarché</strong><br><br>"
            ."📌 <strong>Objet :</strong> {$tender->title}<br>"
            ."🏛 <strong>Institution :</strong> {$tender->institution}<br>"
            ."💰 <strong>Montant :</strong> ".($tender->estimated_amount ?: 'Non communiqué')."<br>"
            ."📅 <strong>Date limite :</strong> ".($tender->deadline?->format('d/m/Y') ?: 'Non communiquée')."<br>"
            ."🌍 <strong>Pays :</strong> {$pays}<br><br>";

        if ($tender->ai_summary) {
            $message .= "📝 {$tender->ai_summary}<br><br>";
        }

        $message .= '👉 <strong>Voir sur le site officiel :</strong> <a href="'.htmlspecialchars($tender->source_url, ENT_QUOTES, 'UTF-8').'" style="color:#1a7f5a;text-decoration:underline;">'.htmlspecialchars($tender->source_url, ENT_QUOTES, 'UTF-8').'</a><br><br>'
            .'<p style="margin-top:20px;padding-top:16px;border-top:1px solid #e3ebe7;color:#6b7d77;font-size:13px;">— Alerte envoyée par AlerteMarche.com</p>';

        return $message;
    }

    /** Format message WhatsApp/Email — Besoin Artisan (cahier des charges 5.3). */
    public function formatNeedMessage(ArtisanNeed $need): string
    {
        $pays = ['BJ' => 'Bénin', 'TG' => 'Togo', 'CI' => "Côte d'Ivoire", 'SN' => 'Sénégal'][$need->country] ?? $need->country;
        $loc = $need->locality.($need->region ? ", {$need->region}" : '')." — {$pays}";

        $message = "🔔 <strong>Nouvelle opportunité — AlerteMarché</strong><br><br>"
            ."🛠 <strong>Domaine :</strong> {$need->trade}<br>"
            ."🏢 <strong>Employeur :</strong> ".($need->employer_name ?: 'Entrepreneur privé')."<br>"
            ."👷 <strong>Besoin :</strong> ".($need->people_needed ?: 'Non précisé')."<br>"
            ."📍 <strong>Localité :</strong> {$loc}<br>"
            ."📅 <strong>Date de début souhaitée :</strong> ".($need->start_date?->format('d/m/Y') ?: 'À convenir')."<br><br>"
            ."📞 <strong>Contacter directement :</strong> {$need->contact}<br><br>"
            .'<p style="margin-top:20px;padding-top:16px;border-top:1px solid #e3ebe7;color:#6b7d77;font-size:13px;">— Alerte envoyée par AlerteMarche.com</p>';

        return $message;
    }
}
