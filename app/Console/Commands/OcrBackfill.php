<?php

namespace App\Console\Commands;

use App\Models\Tender;
use App\Services\OpenAIService;
use Illuminate\Console\Command;

/**
 * OCR de rattrapage : lit les PDF d'avis (dao_url) déjà en base pour lesquels
 * la DATE LIMITE ou le MONTANT manque, via GPT-4o Vision (documents scannés
 * inclus). Complète les champs manquants et pose le flag ocr_processed.
 *
 * Utile pour les sources CI/TG (avis scannés FER/AGEROUTE/ARCOP…) dont les
 * métadonnées n'étaient pas extractibles au moment du scraping.
 *
 * ⚠️ Consomme des crédits OpenAI. Résultats mis en cache 30 j par URL.
 *
 * Exemples :
 *   php artisan tenders:ocr-backfill --country=CI --limit=20
 *   php artisan tenders:ocr-backfill --dry-run
 */
class OcrBackfill extends Command
{
    protected $signature = 'tenders:ocr-backfill
        {--country= : Filtrer par pays (ex. CI, TG, BJ)}
        {--limit=50 : Nombre maximum d\'avis à traiter}
        {--dry-run : Lister les avis éligibles sans appeler l\'IA}';

    protected $description = 'OCR de rattrapage des avis existants (date limite + montant) via GPT-4o Vision.';

    public function handle(OpenAIService $ai): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $query = Tender::query()
            ->where('ocr_processed', false)
            ->whereNotNull('dao_url')
            ->where('dao_url', '!=', '')
            ->whereIn('type', ['public', 'prive', 'aac'])
            ->where(function ($q) {
                $q->whereNull('deadline')
                    ->orWhereNull('estimated_amount')
                    ->orWhere('estimated_amount', '');
            });

        if ($country = $this->option('country')) {
            $query->where('country', $country);
        }

        $total = (int) $query->count();
        if ($total === 0) {
            $this->info('Aucun avis éligible à l\'OCR.');

            return self::SUCCESS;
        }

        $this->info("Avis éligibles : {$total}. Traitement de ".min($total, $limit)." au maximum.");

        if ($this->option('dry-run')) {
            $query->limit($limit)->get(['id', 'country', 'type', 'dao_url'])
                ->each(fn ($t) => $this->line("#{$t->id} [{$t->country}/{$t->type}] {$t->dao_url}"));

            return self::SUCCESS;
        }

        $enriched = 0;
        $query->limit($limit)->get()->each(function (Tender $tender) use ($ai, &$enriched) {
            $meta = $ai->extractPdfMeta($tender->dao_url);
            $update = ['ocr_processed' => true];
            if ($meta) {
                if (empty($tender->deadline) && ! empty($meta['deadline'])) {
                    $update['deadline'] = $meta['deadline'];
                }
                if (empty($tender->estimated_amount) && ! empty($meta['estimated_amount'])) {
                    $update['estimated_amount'] = $meta['estimated_amount'];
                }
            }
            $tender->update($update);
            if (count($update) > 1) {
                $enriched++;
                $this->line("#{$tender->id} ✔ ".json_encode(array_diff_key($update, ['ocr_processed' => true]), JSON_UNESCAPED_UNICODE));
            } else {
                $this->line("#{$tender->id} — aucune donnée extraite");
            }
        });

        $this->info("Terminé. Avis enrichis : {$enriched}.");

        return self::SUCCESS;
    }
}
