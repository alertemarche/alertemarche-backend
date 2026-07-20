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
 * Purge automatique des appels d'offres expirés.
 *
 * Supprime les tenders dont la date limite (deadline) est dépassée depuis
 * plus de 24 heures. Les tenders sans deadline (NULL) ne sont JAMAIS supprimés
 * (sécurité : on ne purge que ce dont on connaît la date d'expiration).
 */
class PurgeExpiredTenders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        // Seuil : deadline dépassée depuis plus de 24 heures.
        $threshold = now()->subHours(24);

        $deleted = Tender::whereNotNull('deadline')
            ->where('deadline', '<', $threshold)
            ->delete();

        Log::info('PurgeExpiredTenders: purge terminée', [
            'deleted' => $deleted,
            'threshold' => $threshold->toDateTimeString(),
        ]);
    }
}
