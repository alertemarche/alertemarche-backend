<?php

namespace App\Jobs;

use App\Models\Tender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Purge automatique des appels d'offres expirés ou obsolètes.
 *
 * Deux règles de nettoyage :
 *
 * 1) Marchés AVEC date limite : supprimés 24h après la date limite dépassée.
 *
 * 2) Marchés SANS date limite : supprimés 30 jours après le dernier passage
 *    du scraper (colonne last_seen_at). Si last_seen_at est vide (marchés
 *    antérieurs à la mise en place du suivi), on se rabat sur collected_at
 *    puis created_at. Certaines sources (Délégations UE, bailleurs
 *    internationaux…) ne fournissent pas de date limite exploitable.
 *
 *    EXCEPTION : les plans de passation et avis généraux restent pertinents
 *    sur une longue durée et ne sont jamais purgés par cette 2e règle.
 */
class PurgeExpiredTenders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Fenêtre de rétention (jours) pour les marchés sans date limite. */
    public const NO_DEADLINE_RETENTION_DAYS = 30;

    /** Types toujours conservés (pertinents sur le long terme). */
    public const KEEP_TYPES = ['plan_passation', 'avis_general'];

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        // --- Règle 1 : deadline dépassée depuis plus de 24 heures. ---
        $deadlineThreshold = now()->subHours(24);

        $deletedWithDeadline = Tender::whereNotNull('deadline')
            ->where('deadline', '<', $deadlineThreshold)
            ->delete();

        // --- Règle 2 : sans deadline, non revus depuis plus de 30 jours. ---
        // On préfère last_seen_at (mis à jour à chaque re-collecte).
        // Fallback sur collected_at puis created_at pour les anciens marchés.
        $staleThreshold = now()->subDays(self::NO_DEADLINE_RETENTION_DAYS);

        $deletedNoDeadline = Tender::whereNull('deadline')
            ->whereNotIn('type', self::KEEP_TYPES)
            ->where(function ($q) use ($staleThreshold) {
                // Cas 1 : last_seen_at renseigné et trop vieux
                $q->where(function ($sub) use ($staleThreshold) {
                    $sub->whereNotNull('last_seen_at')
                        ->where('last_seen_at', '<', $staleThreshold);
                })
                // Cas 2 : last_seen_at absent → fallback collected_at / created_at
                ->orWhere(function ($sub) use ($staleThreshold) {
                    $sub->whereNull('last_seen_at')
                        ->where(function ($sub2) use ($staleThreshold) {
                            $sub2->where('collected_at', '<', $staleThreshold)
                                 ->orWhere(function ($sub3) use ($staleThreshold) {
                                     $sub3->whereNull('collected_at')
                                          ->where('created_at', '<', $staleThreshold);
                                 });
                        });
                });
            })
            ->delete();

        // --- Règle 3 : plans de passation & avis généraux SANS date limite,
        //     d'une ANNÉE RÉVOLUE. ---
        // Un plan de passation est une prévision annuelle : un plan 2026 n'a plus
        // aucune valeur en 2027 (l'État publie alors ses nouveaux plans). On les
        // conserve donc TOUTE leur année de référence, puis on les supprime dès
        // le passage à l'année suivante.
        //
        // IMPORTANT : on ne vise ici que les plans/avis SANS date limite. Ceux
        // qui possèdent une date limite (ex. avis généraux avec échéance
        // 31/12/2026) sont déjà supprimés automatiquement par la Règle 1 le
        // lendemain de leur échéance — les inclure ici les supprimerait à tort
        // (certains sont publiés fin décembre pour l'année suivante).
        //
        // L'année de référence est déduite de la date de publication, avec repli
        // sur collected_at puis created_at.
        $currentYear = (int) now()->year;

        $deletedOldPlans = Tender::whereIn('type', self::KEEP_TYPES)
            ->whereNull('deadline')
            ->whereRaw(
                'EXTRACT(YEAR FROM COALESCE(publication_date, collected_at, created_at)) < ?',
                [$currentYear]
            )
            ->delete();

        Log::info('PurgeExpiredTenders: purge terminée', [
            'deleted_with_deadline' => $deletedWithDeadline,
            'deleted_no_deadline'   => $deletedNoDeadline,
            'deleted_old_plans'     => $deletedOldPlans,
            'deadline_threshold'    => $deadlineThreshold->toDateTimeString(),
            'stale_threshold'       => $staleThreshold->toDateTimeString(),
            'current_year'          => $currentYear,
        ]);
    }
}
