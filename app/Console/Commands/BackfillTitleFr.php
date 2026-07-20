<?php

namespace App\Console\Commands;

use App\Models\Tender;
use App\Services\OpenAIService;
use Illuminate\Console\Command;

/**
 * Backfill : renseigne `title_fr` (titre francisé) pour les avis déjà en base
 * qui n'en ont pas encore. Utile pour les avis privés (UNGM) collectés avant
 * l'ajout de la traduction automatique du titre.
 *
 * Exemples :
 *   php artisan tenders:backfill-title-fr           (tous les avis sans title_fr)
 *   php artisan tenders:backfill-title-fr --type=prive
 *   php artisan tenders:backfill-title-fr --country=BJ --limit=200
 */
class BackfillTitleFr extends Command
{
    protected $signature = 'tenders:backfill-title-fr
        {--type= : Filtrer par type (prive, public, aac, avis_general, plan_passation)}
        {--country= : Filtrer par pays (ex. BJ)}
        {--limit=500 : Nombre maximum d\'avis à traiter}';

    protected $description = 'Traduit en français le titre des avis existants (colonne title_fr).';

    public function handle(OpenAIService $ai): int
    {
        $query = Tender::query()
            ->where(function ($q) {
                $q->whereNull('title_fr')->orWhere('title_fr', '');
            });

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }
        if ($country = $this->option('country')) {
            $query->where('country', $country);
        }

        $limit = (int) $this->option('limit');
        $tenders = $query->limit($limit)->get();

        if ($tenders->isEmpty()) {
            $this->info('Aucun avis à traiter.');

            return self::SUCCESS;
        }

        $this->info("Traduction de {$tenders->count()} titre(s)...");
        $bar = $this->output->createProgressBar($tenders->count());
        $bar->start();

        $done = 0;
        foreach ($tenders as $tender) {
            $titleFr = $ai->translateTitle((string) $tender->title);
            $tender->update(['title_fr' => $titleFr !== '' ? $titleFr : $tender->title]);
            $done++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Terminé : {$done} titre(s) francisé(s).");

        return self::SUCCESS;
    }
}
