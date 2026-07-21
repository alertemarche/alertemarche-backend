<?php

namespace App\Console\Commands;

use App\Models\Tender;
use App\Support\SectorClassifier;
use Illuminate\Console\Command;

/**
 * Re-tagge les secteurs des marchés existants avec le référentiel canonique
 * (config/sectors.php). 100 % local, aucun appel IA → aucun coût.
 *
 * Exemples :
 *   php artisan tenders:retag-sectors                 (tous les marchés)
 *   php artisan tenders:retag-sectors --type=plan_passation
 *   php artisan tenders:retag-sectors --only-empty    (uniquement ceux sans secteur)
 */
class RetagSectors extends Command
{
    protected $signature = 'tenders:retag-sectors
        {--type= : Filtrer par type de marché}
        {--country= : Filtrer par pays (ex. BJ)}
        {--only-empty : Ne traiter que les marchés sans secteur}';

    protected $description = 'Re-classe les secteurs des marchés existants (référentiel canonique, sans IA).';

    public function handle(): int
    {
        $query = Tender::query();

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }
        if ($country = $this->option('country')) {
            $query->where('country', $country);
        }
        if ($this->option('only-empty')) {
            $query->where(function ($q) {
                $q->whereNull('sectors')->orWhere('sectors', '[]');
            });
        }

        $total = (int) $query->count();
        if ($total === 0) {
            $this->info('Aucun marché à re-tagger.');

            return self::SUCCESS;
        }

        $this->info("Re-taggage de {$total} marché(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $changed = 0;
        $query->chunkById(500, function ($tenders) use (&$changed, $bar) {
            foreach ($tenders as $tender) {
                $new = SectorClassifier::classify($tender->title_fr ?: $tender->title, $tender->market_type);
                $old = (array) $tender->sectors;
                sort($new);
                sort($old);
                if ($new !== $old) {
                    $tender->update(['sectors' => $new]);
                    $changed++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Terminé : {$changed} marché(s) mis à jour sur {$total}.");

        return self::SUCCESS;
    }
}
