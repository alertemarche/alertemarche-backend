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

// E-mail GROUPÉ quotidien des nouvelles opportunités (une seule notification
// par utilisateur, par pays, sans lien vers le marché). Envoyé chaque matin.
Schedule::command('alerts:send-daily-digest')
    ->dailyAt('08:00')
    ->name('send-daily-digest')
    ->withoutOverlapping();

// Suppression automatique quotidienne des abonnements annulés (statut « cancelled »).
// Ils ne servent plus à rien une fois annulés : on les retire définitivement de la base.
Schedule::call(function () {
    Subscription::where('status', 'cancelled')->delete();
})->daily()->name('purge-cancelled-subscriptions')->withoutOverlapping();

// Expiration des annonces gratuites (15j) et premium (30j)
Schedule::call(function () {
    \App\Models\ArtisanNeed::where('expires_at', '<', now())
        ->whereIn('status', ['approved', 'pending'])
        ->update(['status' => 'expired']);
})->daily()->name('expire-annonces')->withoutOverlapping();

// Monitoring automatique du système (détecte les blocages et envoie une alerte email)
Schedule::command('system:check-health')
    ->everyFifteenMinutes()
    ->name('check-system-health')
    ->withoutOverlapping();
