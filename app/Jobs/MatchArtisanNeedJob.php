<?php

namespace App\Jobs;

use App\Models\ArtisanNeed;
use App\Services\MatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MatchArtisanNeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'matching';

    public function __construct(public int $needId) {}

    public function handle(MatchingService $matcher): void
    {
        $need = ArtisanNeed::find($this->needId);
        if (! $need || $need->status !== 'approved') {
            return;
        }

        foreach ($matcher->artisansForNeed($need) as $user) {
            SendNeedAlertJob::dispatch($user->id, $need->id)->onQueue('notifications');
        }
    }
}
