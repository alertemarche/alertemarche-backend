<?php
/**
 * Rappel d'abonnement — pour les PROS DÉJÀ INSCRITS mais NON ABONNÉS.
 *
 * Contrairement à send_recovery.php, ce script :
 *   - NE SUPPRIME AUCUN COMPTE (les pros sont déjà inscrits et vérifiés) ;
 *   - cible uniquement les comptes vérifiés SANS abonnement actif ;
 *   - envoie un lien vers /connexion?redirect=tarifs afin que le pro se
 *     CONNECTE (il est déjà inscrit) puis arrive DIRECTEMENT sur la page
 *     d'abonnement — plus jamais renvoyé vers le formulaire d'inscription.
 *
 * Exécution DANS le conteneur :
 *   # Simulation (aucun envoi) :
 *   docker exec alertemarche_app php /var/www/html/send_subscription_reminder.php --dry-run
 *   # Envoi réel :
 *   docker exec alertemarche_app php /var/www/html/send_subscription_reminder.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\BrevoService;

$DRY_RUN = in_array('--dry-run', $argv, true);

// Lien de reprise : connexion PUIS redirection automatique vers page d'incitation → abonnement.
$CTA_URL = 'https://alertemarche.com/connexion?redirect=offre-speciale';

$brevo = app(BrevoService::class);

// Comptes inscrits & vérifiés, avec un e-mail, hors administrateurs.
$users = User::whereNotNull('email')
    ->whereNotNull('email_verified_at')
    ->where(fn ($q) => $q->whereNull('is_admin')->orWhere('is_admin', false))
    ->orderBy('id')
    ->get();

$targets = $users->filter(fn (User $u) => ! $u->hasActiveSubscription());

echo ($DRY_RUN ? "[SIMULATION] " : "") . "Pros inscrits non abonnés : " . $targets->count() . PHP_EOL;

$sent = [];
$failed = [];

foreach ($targets as $u) {
    $prenom = trim(explode(' ', trim((string) $u->name))[0] ?? '');
    $prenom = $prenom !== '' ? $prenom : 'Cher professionnel';

    $subject = "🔔 AlerteMarché — Activez votre abonnement en 1 clic";

    $html = '<div style="font-family:Inter,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2937;">'
        . '<p>Bonjour ' . htmlspecialchars($prenom, ENT_QUOTES) . ',</p>'
        . '<p>Votre compte <b>AlerteMarché</b> est bien actif, mais vous n\'avez pas encore '
        . 'd\'<b>abonnement</b>. Sans abonnement, l\'accès aux détails des marchés (budget, date '
        . 'limite, référence, lien officiel) et les alertes automatiques restent bloqués.</p>'
        . '<p style="background:#ecfdf5;border-left:4px solid #16a34a;padding:12px 16px;border-radius:6px;">'
        . '<b>Bonne nouvelle : nos tarifs ont baissé.</b> Formules dès <b>5 000 FCFA / semaine</b> '
        . 'ou <b>17 700 FCFA / mois</b>.</p>'
        . '<p>Vous êtes déjà inscrit : <b>connectez-vous simplement</b> et vous arriverez '
        . 'directement sur la page d\'abonnement.</p>'
        . '<p style="text-align:center;margin:28px 0;">'
        . '<a href="' . $CTA_URL . '" '
        . 'style="background:#0f7a3d;color:#fff;font-weight:700;padding:14px 30px;border-radius:8px;'
        . 'text-decoration:none;display:inline-block;">Activer mon abonnement →</a></p>'
        . '<p style="font-size:13px;color:#6b7280;">Si le bouton ne fonctionne pas, copiez ce lien dans '
        . 'votre navigateur :<br>' . $CTA_URL . '</p>'
        . '<p>À très vite,<br><b>L\'équipe AlerteMarché</b></p>'
        . '</div>';

    if ($DRY_RUN) {
        echo "  [SIMULATION] -> {$u->email} (prénom: {$prenom})" . PHP_EOL;
        $sent[] = $u->email;
        continue;
    }

    $ok = false;
    try {
        $ok = $brevo->send($u->email, $u->name, $subject, $html);
    } catch (\Throwable $e) {
        echo "  ERREUR envoi {$u->email} : " . $e->getMessage() . PHP_EOL;
    }

    if ($ok) {
        $sent[] = $u->email;
        echo "  OK    -> {$u->email} (prénom: {$prenom})" . PHP_EOL;
    } else {
        $failed[] = $u->email;
        echo "  ECHEC -> {$u->email}" . PHP_EOL;
    }
    usleep(300000); // 0.3s entre envois
}

echo PHP_EOL . "=== RESUME ===" . PHP_EOL;
echo ($DRY_RUN ? "À envoyer" : "Envoyés") . " : " . count($sent) . PHP_EOL;
if (! $DRY_RUN) {
    echo "Echecs  : " . count($failed) . PHP_EOL;
    if ($failed) {
        echo "Adresses en échec : " . implode(', ', $failed) . PHP_EOL;
    }
}
echo "IMPORTANT : aucun compte n'a été supprimé (les pros restent inscrits)." . PHP_EOL;
echo "=== FIN ===" . PHP_EOL;
