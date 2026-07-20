<?php

namespace App\Jobs;

use App\Models\Tender;
use App\Services\MatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Matching direct : identifie les prestataires/admin/ong concernés
 * puis planifie l'envoi des alertes.
 */
class MatchTenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'matching';

    public function __construct(public int $tenderId) {}

    public function handle(MatchingService $matcher): void
    {
        $tender = Tender::find($this->tenderId);
        if (! $tender) {
            return;
        }

        foreach ($matcher->usersForTender($tender) as $user) {
            SendTenderAlertJob::dispatch($user->id, $tender->id)->onQueue('notifications');
        }
    }
}
