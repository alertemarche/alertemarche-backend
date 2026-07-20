<?php

use App\Jobs\ProcessTenderJob;
use App\Models\Tender;
use Illuminate\Support\Facades\Schedule;

// Retraitement des appels d'offres non analysés (filet de sécurité).
Schedule::call(function () {
    Tender::where('ai_processed', false)->limit(50)->get()
        ->each(fn ($t) => ProcessTenderJob::dispatch($t->id)->onQueue('ai'));
})->everyThirtyMinutes()->name('reprocess-tenders')->withoutOverlapping();
