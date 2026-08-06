<?php

use App\Jobs\ProcessTenderJob;
use App\Jobs\PurgeExpiredTenders;
use App\Models\Subscription;
use App\Models\Tender;
use Illuminate\Support\Facades\Schedule;

// Retraitement des appels d'offres non analysés (filet de sécurité).
Schedule::call(function () {
    Tender::where('ai_processed', false)->limit(50)->get()
        ->each(fn ($t) => ProcessTenderJob::dispatch($t->id)->onQueue('ai'));
})->everyThirtyMinutes()->name('reprocess-tenders')->withoutOverlapping();

// Purge quotidienne des appels d'offres expirés (deadline dépassée depuis +24h).
Schedule::job(new PurgeExpiredTenders())
    ->daily()
    ->name('purge-expired-tenders')
    ->withoutOverlapping();

// Suppression automatique quotidienne des abonnements annulés (statut « cancelled »).
// Ils ne servent plus à rien une fois annulés : on les retire définitivement de la base.
Schedule::call(function () {
    Subscription::where('status', 'cancelled')->delete();
})->daily()->name('purge-cancelled-subscriptions')->withoutOverlapping();
