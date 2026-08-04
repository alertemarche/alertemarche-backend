<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTenderJob;
use App\Models\ScraperLog;
use App\Models\Tender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Réception des opportunités collectées par les robots (API interne authentifiée).
 * Déduplication par hash (objet + institution + date limite).
 */
class IngestController extends Controller
{
    public function tenders(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.title' => ['required', 'string'],
            'items.*.institution' => ['required', 'string'],
            'items.*.reference' => ['nullable', 'string'],
            'items.*.location' => ['nullable', 'string'],
            'items.*.estimated_amount' => ['nullable', 'string'],
            'items.*.deadline' => ['nullable', 'date'],
            'items.*.publication_date' => ['nullable', 'date'],
            'items.*.nb_lots' => ['nullable', 'integer'],
            'items.*.country' => ['required', Rule::in(['BJ', 'TG', 'CI', 'SN'])],
            'items.*.type' => ['nullable', Rule::in(['public', 'prive', 'aac', 'avis_general', 'plan_passation'])],
            'items.*.market_type' => ['nullable', 'string'],
            'items.*.procedure_type' => ['nullable', 'string'],
            'items.*.source_name' => ['nullable', 'string'],
            'items.*.source_url' => ['required', 'string'],
            'items.*.dao_url' => ['nullable', 'string'],
            'items.*.external_id' => ['nullable', 'string'],
        ]);

        $new = 0;
        $updated = 0;
        foreach ($data['items'] as $item) {
            // Clé de déduplication : identifiant source si disponible (permet la
            // détection de modifications/addendum), sinon hash objet+institution+deadline.
            $hash = !empty($item['external_id'])
                ? hash('sha256', $item['country'].'|'.$item['external_id'])
                : hash('sha256', mb_strtolower(trim($item['title'])).'|'.mb_strtolower(trim($item['institution'])).'|'.($item['deadline'] ?? ''));

            $attributes = [
                'title' => $item['title'],
                // Teaser calculé dès le scraping : objet conservé, acheteur masqué
                // en fin. Recalculé après la traduction FR par ProcessTenderJob.
                'teaser_title' => Tender::teaserTitle($item['title']),
                'institution' => $item['institution'],
                'reference' => $item['reference'] ?? null,
                'location' => $item['location'] ?? null,
                'estimated_amount' => $item['estimated_amount'] ?? null,
                'deadline' => $item['deadline'] ?? null,
                'publication_date' => $item['publication_date'] ?? null,
                'nb_lots' => $item['nb_lots'] ?? null,
                'country' => $item['country'],
                'type' => $item['type'] ?? 'public',
                'market_type' => $item['market_type'] ?? null,
                'procedure_type' => $item['procedure_type'] ?? null,
                'source_name' => $item['source_name'] ?? null,
                'source_url' => $item['source_url'],
                'dao_url' => $item['dao_url'] ?? null,
                'external_id' => $item['external_id'] ?? null,
            ];

            $tender = Tender::where('dedup_hash', $hash)->first();

            if (!$tender) {
                $tender = Tender::create(array_merge($attributes, [
                    'dedup_hash' => $hash,
                    'collected_at' => now(),
                ]));
                $new++;
                ProcessTenderJob::dispatch($tender->id)->onQueue('ai');
            } else {
                // Détection de modification (addendum, changement de date limite…).
                $changed = $tender->deadline?->format('Y-m-d') !== ($item['deadline'] ?? null)
                    || $tender->title !== $item['title'];
                $tender->fill($attributes)->save();
                if ($changed) {
                    $updated++;
                }
            }
        }

        return response()->json(['received' => count($data['items']), 'new' => $new, 'updated' => $updated]);
    }

    /** Journalisation d'une exécution de scraper. */
    public function log(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country' => ['required', Rule::in(['BJ', 'TG', 'CI', 'SN'])],
            'source_name' => ['required', 'string'],
            'status' => ['required', Rule::in(['success', 'failure'])],
            'items_collected' => ['nullable', 'integer'],
            'items_new' => ['nullable', 'integer'],
            'message' => ['nullable', 'string'],
        ]);

        $log = ScraperLog::create([...$data, 'ran_at' => now()]);

        return response()->json(['logged' => true, 'id' => $log->id]);
    }
}
