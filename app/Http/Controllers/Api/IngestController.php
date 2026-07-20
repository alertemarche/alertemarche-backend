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
            'items.*.estimated_amount' => ['nullable', 'string'],
            'items.*.deadline' => ['nullable', 'date'],
            'items.*.country' => ['required', Rule::in(['BJ', 'TG', 'CI'])],
            'items.*.type' => ['nullable', Rule::in(['public', 'prive'])],
            'items.*.source_name' => ['nullable', 'string'],
            'items.*.source_url' => ['required', 'string'],
        ]);

        $new = 0;
        foreach ($data['items'] as $item) {
            $hash = hash('sha256', mb_strtolower(trim($item['title'])).'|'.mb_strtolower(trim($item['institution'])).'|'.($item['deadline'] ?? ''));

            $tender = Tender::firstOrCreate(
                ['dedup_hash' => $hash],
                [
                    'title' => $item['title'],
                    'institution' => $item['institution'],
                    'estimated_amount' => $item['estimated_amount'] ?? null,
                    'deadline' => $item['deadline'] ?? null,
                    'country' => $item['country'],
                    'type' => $item['type'] ?? 'public',
                    'source_name' => $item['source_name'] ?? null,
                    'source_url' => $item['source_url'],
                    'collected_at' => now(),
                ]
            );

            if ($tender->wasRecentlyCreated) {
                $new++;
                ProcessTenderJob::dispatch($tender->id)->onQueue('ai');
            }
        }

        return response()->json(['received' => count($data['items']), 'new' => $new]);
    }

    /** Journalisation d'une exécution de scraper. */
    public function log(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country' => ['required', Rule::in(['BJ', 'TG', 'CI'])],
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
