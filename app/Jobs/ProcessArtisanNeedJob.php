<?php

namespace App\Jobs;

use App\Models\ArtisanNeed;
use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Étape IA pour un besoin artisan approuvé, puis matching inverse.
 */
class ProcessArtisanNeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $needId)
    {
        $this->onQueue('ai');
    }

    public function handle(OpenAIService $ai): void
    {
        $need = ArtisanNeed::find($this->needId);
        if (! $need || $need->ai_processed) {
            return;
        }

        $result = $ai->summarizeArtisanNeed([
            'trade' => $need->trade,
            'employer_name' => $need->employer_name,
            'people_needed' => $need->people_needed,
            'locality' => $need->locality,
            'country' => $need->country,
            'description' => $need->description,
        ]);

        $need->update([
            'ai_summary' => $result['summary'] ?? null,
            'ai_processed' => true,
        ]);

        MatchArtisanNeedJob::dispatch($need->id)->onQueue('matching');
    }
}
