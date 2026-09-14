<?php

namespace App\Console\Commands;

use App\Models\Tender;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recalcule la colonne `dedup_hash` de tous les avis avec la logique de
 * déduplication BASÉE SUR LE CONTENU (pays + objet + acheteur + date limite),
 * identique à celle d'IngestController. Indispensable après le passage de la
 * déduplication « external_id » à la déduplication « contenu » : sans ce
 * backfill, le prochain scrape ne reconnaîtrait pas les avis existants et
 * recréerait un doublon par marché.
 *
 * En cas de collision (plusieurs lignes produisant le même hash de contenu),
 * on conserve la ligne la plus récente et on supprime les autres.
 *
 * Exemples :
 *   php artisan tenders:recompute-dedup-hash
 *   php artisan tenders:recompute-dedup-hash --dry-run
 */
class RecomputeDedupHash extends Command
{
    protected $signature = 'tenders:recompute-dedup-hash
        {--dry-run : Affiche ce qui serait fait sans rien modifier}';

    protected $description = 'Recalcule dedup_hash (dédup contenu) et supprime les doublons résiduels.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $seen = [];        // hash => id conservé
        $updated = 0;
        $deleted = 0;

        // Chargement en une seule passe (colonnes utiles uniquement). On trie du
        // plus récent au plus ancien : la 1re occurrence d'un hash de contenu est
        // la ligne à conserver, les suivantes sont des doublons à supprimer.
        // NB : on n'utilise PAS chunkById ici car il impose son propre tri par id
        // et casserait l'ordre « plus récent d'abord ».
        $tenders = Tender::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'country', 'title', 'institution', 'deadline', 'dedup_hash']);

        $this->info("Traitement de {$tenders->count()} avis…");

        $toDelete = [];
        foreach ($tenders as $t) {
            $deadlineKey = '';
            if (!empty($t->deadline)) {
                try {
                    $deadlineKey = Carbon::parse($t->deadline)->format('Y-m-d');
                } catch (\Throwable $e) {
                    $deadlineKey = (string) $t->deadline;
                }
            }
            $hash = hash('sha256',
                $t->country.'|'.
                mb_strtolower(trim((string) $t->title)).'|'.
                mb_strtolower(trim((string) $t->institution)).'|'.
                $deadlineKey
            );

            if (isset($seen[$hash])) {
                $deleted++;
                $toDelete[] = $t->id;
                continue;
            }

            $seen[$hash] = $t->id;
            if ($t->dedup_hash !== $hash) {
                $updated++;
                if (!$dryRun) {
                    DB::table('tenders')->where('id', $t->id)->update(['dedup_hash' => $hash]);
                }
            }
        }

        if (!$dryRun && !empty($toDelete)) {
            foreach (array_chunk($toDelete, 500) as $batch) {
                DB::table('tenders')->whereIn('id', $batch)->delete();
            }
        }

        $mode = $dryRun ? '[DRY-RUN] ' : '';
        $this->info("{$mode}dedup_hash recalculés : {$updated}");
        $this->info("{$mode}doublons résiduels supprimés : {$deleted}");

        return self::SUCCESS;
    }
}
