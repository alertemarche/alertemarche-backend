<?php

namespace App\Jobs;

use App\Models\Tender;
use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Étape IA : génère le résumé structuré GPT-4o d'un appel d'offres,
 * classe les secteurs, puis déclenche le matching.
 */
class ProcessTenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $tenderId)
    {
        $this->onQueue('ai');
    }

    public function handle(OpenAIService $ai): void
    {
        $tender = Tender::find($this->tenderId);
        if (! $tender || $tender->ai_processed) {
            return;
        }

        $result = $ai->summarizeTender([
            'title' => $tender->title,
            'institution' => $tender->institution,
            'estimated_amount' => $tender->estimated_amount,
            'deadline' => $tender->deadline?->format('Y-m-d'),
            'country' => $tender->country,
        ]);

        $titleFr = trim((string) ($result['title_fr'] ?? ''));

        $tender->update([
            'title_fr' => $titleFr !== '' ? $titleFr : $tender->title,
            'ai_summary' => $result['summary'] ?? null,
            'sectors' => $result['sectors'] ?? [],
            'ai_processed' => true,
        ]);

        MatchTenderJob::dispatch($tender->id)->onQueue('matching');
    }
}
