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

        // Chemin économe : les plans de passation (opérations planifiées de la
        // DNCMP) sont déjà rédigés en français et remontent en très grand nombre.
        // Les faire passer par GPT-4o coûterait cher sans réelle valeur ajoutée.
        // On les classe localement (secteurs par mots-clés) et on les marque
        // traités, sans appel API. Les avis formels (public/privé, parfois en
        // anglais) continuent d'être résumés/traduits par l'IA.
        if ($tender->type === 'plan_passation') {
            $this->processLocally($tender);
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

    /**
     * Traitement local (sans IA) pour les opérations planifiées : titre déjà
     * en français, secteurs déduits par mots-clés, résumé factuel concis.
     */
    protected function processLocally(Tender $tender): void
    {
        $sectors = $this->guessSectors($tender->title, $tender->market_type);

        $parts = [];
        if ($tender->market_type) {
            $parts[] = 'Marché de '.mb_strtolower($tender->market_type);
        }
        if ($tender->institution) {
            $parts[] = 'porté par '.$tender->institution;
        }
        $summary = 'Opération inscrite au plan de passation des marchés';
        if ($parts) {
            $summary .= ' ('.implode(', ', $parts).')';
        }
        $summary .= '. Consultez la source officielle (DNCMP) pour le détail et le calendrier prévisionnel.';

        $tender->update([
            'title_fr' => $tender->title,
            'ai_summary' => $summary,
            'sectors' => $sectors,
            'ai_processed' => true,
        ]);

        // NB : pas de matching/alerte ici. Les plans de passation sont des
        // opérations PRÉVISIONNELLES (calendrier planifié), remontées en très
        // grand nombre. On les rend consultables sur la plateforme, mais on ne
        // déclenche pas d'alerte WhatsApp/Email par ligne pour éviter d'inonder
        // les abonnés et de générer des coûts d'envoi inutiles. Les alertes
        // restent réservées aux avis formels (public/privé/aac).
    }

    /**
     * Classification sectorielle locale par mots-clés.
     * S'appuie sur le référentiel canonique unique (config/sectors.php)
     * via App\Support\SectorClassifier — 21 secteurs dérivés des intitulés
     * réels de marchés béninois.
     */
    protected function guessSectors(?string $title, ?string $marketType): array
    {
        return \App\Support\SectorClassifier::classify($title, $marketType);
    }
}
