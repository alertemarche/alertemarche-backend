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

        Log::info('PurgeExpiredTenders: purge terminée', [
            'deleted_with_deadline' => $deletedWithDeadline,
            'deleted_no_deadline'   => $deletedNoDeadline,
            'deadline_threshold'    => $deadlineThreshold->toDateTimeString(),
            'stale_threshold'       => $staleThreshold->toDateTimeString(),
        ]);
    }
}
