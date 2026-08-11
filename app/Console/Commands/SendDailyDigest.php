<?php

namespace App\Console\Commands;

use App\Services\AlertDispatcher;
use Illuminate\Console\Command;

/**
 * Envoie l'e-mail GROUPÉ quotidien des nouvelles opportunités.
 *
 * Pour chaque utilisateur ayant des marchés en file d'attente (alertes au
 * statut « queued »), un SEUL e-mail est envoyé, listant le nombre de marchés
 * par pays, SANS aucun lien vers le marché : l'utilisateur est invité à se
 * connecter au site pour consulter les détails.
 *
 * Aucun mélange de pays : le matching ne rattache un marché qu'aux
 * utilisateurs de son pays, l'e-mail reflète donc uniquement les marchés du
 * (des) pays de l'utilisateur.
 *
 * Exécution planifiée : tous les jours à 08:00 (voir routes/console.php).
 *
 * Usage manuel :
 *   php artisan alerts:send-daily-digest
 */
class SendDailyDigest extends Command
{
    protected $signature = 'alerts:send-daily-digest';

    protected $description = "Envoie l'e-mail groupé quotidien des nouvelles opportunités (sans lien vers le marché).";

    public function handle(AlertDispatcher $dispatcher): int
    {
        $this->info('Envoi des e-mails groupés quotidiens...');

        $stats = $dispatcher->sendDailyDigests();

        $this->info(sprintf(
            'Terminé : %d utilisateur(s) traité(s), %d e-mail(s) envoyé(s), %d marché(s) annoncé(s).',
            $stats['users'],
            $stats['sent'],
            $stats['tenders'],
        ));

        return self::SUCCESS;
    }
}
