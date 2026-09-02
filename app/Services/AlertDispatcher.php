<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\ArtisanNeed;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Diffusion d'une alerte à un abonné en respectant :
 *  - le plafond quotidien de marchés (config alertemarche.daily_alerts) ;
 *  - la règle « 1 opportunité matchée = 1 alerte consommée » ;
 *  - l'envoi d'un e-mail GROUPÉ quotidien pour les marchés (sans lien vers
 *    le marché) : l'utilisateur est invité à se connecter au site pour
 *    consulter les détails.
 */
class AlertDispatcher
{
    /** Libellés des pays couverts (code ISO → nom FR). */
    public const COUNTRIES = [
        'BJ' => 'Bénin',
        'TG' => 'Togo',
        'CI' => "Côte d'Ivoire",
        'SN' => 'Sénégal',
        'BF' => 'Burkina Faso',
    ];

    /** Préposition correcte devant le nom du pays (« au Bénin », « en Côte d'Ivoire »). */
    protected const COUNTRY_PREP = [
        'BJ' => 'au',
        'TG' => 'au',
        'CI' => 'en',
        'SN' => 'au',
        'BF' => 'au',
    ];

    public function __construct(
        protected BrevoService $brevo,
        protected WhatsAppService $whatsapp,
    ) {}

    /**
     * Met un appel d'offres en file d'attente pour l'utilisateur.
     *
     * NOUVEAU MODÈLE : on n'envoie plus un e-mail détaillé (avec lien) par
     * marché. Le marché est enregistré avec le statut « queued » ; un e-mail
     * GROUPÉ quotidien (sans lien vers le marché) est ensuite envoyé par la
     * commande « alerts:send-daily-digest ». L'utilisateur se connecte au site
     * pour consulter les détails.
     *
     * Le plafond quotidien (config alertemarche.daily_alerts, défaut 5) reste
     * appliqué en amont via User::canReceiveAlert() : au plus N marchés mis en
     * file par utilisateur et par jour.
     */
    public function dispatchTender(User $user, Tender $tender, float $score = 60.0): ?Alert
    {
        if (! $user->canReceiveAlert()) {
            return null;
        }

        $isFree = ! $user->hasActiveSubscription();

        $alert = Alert::create([
            'user_id' => $user->id,
            'source_type' => 'tender',
            'source_id' => $tender->id,
            'title' => $tender->title,
            // Le contenu réel est un e-mail groupé quotidien sans détails :
            // on conserve ici l'objet du marché à titre de référence interne.
            'message' => $tender->title,
            'relevance_score' => $score,
            'is_free' => $isFree,
            'status' => 'queued',
        ]);

        // Compteur historique (le plafond quotidien est géré par
        // User::canReceiveAlert(), qui compte les alertes créées aujourd'hui).
        if ($isFree) {
            $user->increment('free_alerts_used');
        }

        return $alert;
    }

    /**
     * Envoie l'e-mail GROUPÉ quotidien à tous les utilisateurs ayant des marchés
     * en attente (status = « queued »).
     *
     * Règles :
     *  - un seul e-mail par utilisateur, listant le nombre de marchés PAR PAYS ;
     *  - AUCUN mélange de pays entre utilisateurs : le matching ne rattache déjà
     *    un marché qu'aux utilisateurs de son pays (primary_country) ou d'un
     *    abonnement couvrant ce pays. On regroupe donc par pays réel du marché ;
     *  - aucun lien vers le marché : l'e-mail invite seulement à se connecter.
     *
     * @return array{users:int, sent:int, tenders:int} statistiques d'envoi
     */
    public function sendDailyDigests(): array
    {
        $queued = Alert::where('status', 'queued')
            ->where('source_type', 'tender')
            ->get()
            ->groupBy('user_id');

        $stats = ['users' => 0, 'sent' => 0, 'tenders' => 0];

        foreach ($queued as $userId => $alerts) {
            $stats['users']++;
            $user = User::find($userId);

            // Utilisateur introuvable ou notifications e-mail désactivées :
            // on solde les alertes sans envoyer.
            if (! $user || ! $user->email || ! $user->notify_email) {
                Alert::whereIn('id', $alerts->pluck('id'))->update([
                    'status' => 'skipped',
                    'sent_at' => now(),
                ]);

                continue;
            }

            // Comptage par pays (uniquement les marchés encore présents en base).
            $tenderIds = $alerts->pluck('source_id')->unique()->all();
            $tenders = Tender::whereIn('id', $tenderIds)->get()->keyBy('id');

            $countsByCountry = [];
            foreach ($alerts as $a) {
                $t = $tenders->get($a->source_id);
                if (! $t) {
                    continue; // marché purgé entre-temps (deadline expirée)
                }
                $countsByCountry[$t->country] = ($countsByCountry[$t->country] ?? 0) + 1;
            }

            $total = array_sum($countsByCountry);

            // Plus aucun marché valide à annoncer : on solde sans e-mail.
            if ($total === 0) {
                Alert::whereIn('id', $alerts->pluck('id'))->update([
                    'status' => 'skipped',
                    'sent_at' => now(),
                ]);

                continue;
            }

            [$subject, $body] = $this->buildTenderDigest($user, $countsByCountry, $total);

            $ok = false;
            try {
                $ok = $this->brevo->sendAlert($user->email, $user->name, $subject, $body);
            } catch (\Throwable $e) {
                Log::warning('Digest e-mail echec pour user '.$user->id.' : '.$e->getMessage());
            }

            Alert::whereIn('id', $alerts->pluck('id'))->update([
                'status' => $ok ? 'sent' : 'failed',
                'sent_email' => $ok,
                'sent_at' => now(),
            ]);

            if ($ok) {
                $stats['sent']++;
                $stats['tenders'] += $total;
            }
        }

        return $stats;
    }

    /**
     * Construit l'e-mail groupé (objet + corps HTML) pour un utilisateur.
     *
     * @param  array<string,int>  $countsByCountry  code pays → nombre de marchés
     * @return array{0:string,1:string}  [objet, corps HTML]
     */
    public function buildTenderDigest(User $user, array $countsByCountry, int $total): array
    {
        $prenom = $user->name ? explode(' ', trim($user->name))[0] : 'Bonjour';
        $sMarche = $total > 1 ? 'nouveaux marchés' : 'nouveau marché';
        $sOpp = $total > 1 ? 'opportunités' : 'opportunité';

        $subject = "🔔 {$total} ".($total > 1 ? 'nouvelles opportunités' : 'nouvelle opportunité')
            .' dans votre domaine — AlerteMarché';

        // Ligne par pays : « • 3 marchés au Bénin »
        $lignes = '';
        foreach ($countsByCountry as $code => $n) {
            $pays = self::COUNTRIES[$code] ?? $code;
            $prep = self::COUNTRY_PREP[$code] ?? 'au';
            $motMarche = $n > 1 ? 'marchés' : 'marché';
            $lignes .= "<li style=\"margin:6px 0;\">✅ <strong>{$n}</strong> {$motMarche} {$prep} {$pays}</li>";
        }

        $connexionUrl = 'https://www.alertemarche.com/connexion.html';

        // Bloc pays (encadré vert clair) — un ✅ par pays avec compteur.
        $paysBloc = "<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" "
            ."style=\"background:#f0faf6;border:1px solid #d7ede4;border-radius:10px;margin:20px 0;\">"
            ."<tr><td style=\"padding:16px 22px;\">"
            ."<ul style=\"list-style:none;margin:0;padding:0;font-size:16px;color:#14352a;\">{$lignes}</ul>"
            ."</td></tr></table>";

        // Grand bouton CTA vert centré (compatible Gmail/webmail : balise <a> stylée en inline-block).
        $cta = "<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">"
            ."<tr><td align=\"center\" style=\"padding:8px 0 4px;\">"
            .'<a href="'.$connexionUrl.'" target="_blank" '
            .'style="display:inline-block;background:#1a7f5a;color:#ffffff;padding:16px 40px;'
            .'border-radius:10px;text-decoration:none;font-weight:800;font-size:17px;'
            .'box-shadow:0 3px 10px rgba(26,127,90,.28);">🔔 Consulter mes marchés</a>'
            ."</td></tr></table>";

        $body = "<p style=\"font-size:16px;margin:0 0 14px;\">Bonjour <strong>{$prenom}</strong>,</p>"
            ."<p style=\"font-size:16px;margin:0 0 6px;\">Bonne nouvelle ! <strong>{$total} {$sMarche}</strong> "
            ."correspondant à votre domaine d'activité "
            .($total > 1 ? "viennent d'être publiés" : "vient d'être publié")
            ." sur <strong>AlerteMarché</strong> :</p>"
            .$paysBloc
            ."<p style=\"font-size:16px;margin:0 0 22px;\">Connectez-vous à votre espace pour découvrir "
            ."ces {$sOpp} et consulter tous les détails : <em>objet, institution, montant estimé et date limite</em>.</p>"
            .$cta
            ."<p style=\"font-size:14px;color:#4b5563;text-align:center;margin:18px 0 0;\">"
            ."Ne manquez aucune opportunité dans votre secteur.</p>";

        return [$subject, $body];
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
        // Site 100% GRATUIT : TOUT LE MONDE reçoit le détail complet de
        // l'opportunité par e-mail (plus de « teaser »). Le plafond de 5 alertes
        // par jour est appliqué en amont via User::canReceiveAlert().
        $isFree = ! $user->hasActiveSubscription();
        $emailBody = $message;
        $emailSubject = 'Nouvelle opportunité — AlerteMarché';

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

        // Modèle gratuit : plus de suspension ni de quota « à vie ». Le plafond
        // quotidien (5/jour) est géré par User::canReceiveAlert() et se remet à
        // zéro chaque jour. On conserve le compteur historique à titre indicatif.
        if ($isFree) {
            $user->increment('free_alerts_used');
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
        $url = 'https://www.alertemarche.com/connexion.html';
        $prenom = $user->name ? explode(' ', trim($user->name))[0] : 'Bonjour';

        return "<p style=\"font-size:16px;margin:0 0 14px;\">Bonjour <strong>{$prenom}</strong>,</p>"
            ."<p style=\"font-size:16px;margin:0 0 18px;\">Bonne nouvelle ! De nouveaux appels d'offres et "
            ."opportunités viennent d'être publiés dans votre secteur d'activité sur <strong>AlerteMarché</strong>.</p>"
            ."<p style=\"font-size:16px;margin:0 0 22px;\">Connectez-vous à votre espace pour consulter tous "
            ."les détails : <em>objet, institution, montant estimé et date limite</em>.</p>"
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
            .'<td align="center" style="padding:8px 0 4px;">'
            .'<a href="'.$url.'" target="_blank" style="display:inline-block;background:#1a7f5a;color:#ffffff;'
            .'padding:16px 40px;border-radius:10px;text-decoration:none;font-weight:800;font-size:17px;'
            .'box-shadow:0 3px 10px rgba(26,127,90,.28);">🔔 Consulter mes marchés</a>'
            .'</td></tr></table>'
            ."<p style=\"font-size:14px;color:#4b5563;text-align:center;margin:18px 0 0;\">"
            ."Ne manquez aucune opportunité dans votre secteur.</p>";
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
        $pays = ['BJ' => 'Bénin', 'TG' => 'Togo', 'CI' => "Côte d'Ivoire", 'SN' => 'Sénégal', 'BF' => 'Burkina Faso'][$tender->country] ?? $tender->country;

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
        $pays = ['BJ' => 'Bénin', 'TG' => 'Togo', 'CI' => "Côte d'Ivoire", 'SN' => 'Sénégal', 'BF' => 'Burkina Faso'][$need->country] ?? $need->country;
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
