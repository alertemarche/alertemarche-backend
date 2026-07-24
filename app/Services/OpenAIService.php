<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'analyse IA via OpenAI GPT-4o.
 * - Génère un résumé structuré en français des appels d'offres et besoins artisans.
 * - Classifie les secteurs d'activité.
 * Les résumés sont mis en cache pour maîtriser les coûts API.
 */
class OpenAIService
{
    protected string $key;
    protected string $model;
    protected string $baseUrl;

    public function __construct()
    {
        $this->key = (string) config('services.openai.key');
        $this->model = (string) config('services.openai.model', 'gpt-4o');
        $this->baseUrl = rtrim((string) config('services.openai.base_url'), '/');
    }

    /** Résumé structuré d'un appel d'offres public/privé. */
    public function summarizeTender(array $tender): array
    {
        $cacheKey = 'ai_tender_'.md5(json_encode($tender));

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($tender) {
            $sectorNames = implode(', ', \App\Support\SectorClassifier::names());
            $prompt = "Tu es l'assistant IA d'AlerteMarché, plateforme de veille des appels d'offres en Afrique de l'Ouest.\n"
                ."À partir des métadonnées suivantes :\n"
                ."1. Traduis le titre EN FRANÇAIS de façon fidèle et professionnelle (si le titre est déjà en français, recopie-le tel quel ; conserve les sigles d'organisations et les codes de référence).\n"
                ."2. Produis un résumé PROFESSIONNEL, clair et concis EN FRANÇAIS (4 à 6 phrases max).\n"
                ."3. Identifie le ou les secteurs d'activité concernés STRICTEMENT parmi cette liste (recopie le libellé exact, n'invente aucun autre secteur) : {$sectorNames}.\n\n"
                ."Métadonnées :\n"
                ."- Objet : {$tender['title']}\n"
                ."- Institution : {$tender['institution']}\n"
                ."- Montant estimé : ".($tender['estimated_amount'] ?? 'Non communiqué')."\n"
                ."- Date limite : ".($tender['deadline'] ?? 'Non communiquée')."\n"
                ."- Pays : {$tender['country']}\n\n"
                ."Réponds STRICTEMENT en JSON : {\"title_fr\": \"...\", \"summary\": \"...\", \"sectors\": [\"...\"]}";

            $res = $this->askJson($prompt) ?? [
                'title_fr' => $tender['title'],
                'summary' => $tender['title'].' — '.$tender['institution'].'.',
                'sectors' => [],
            ];

            // Ne conserver que les secteurs présents dans le référentiel canonique.
            $res['sectors'] = \App\Support\SectorClassifier::keepValid((array) ($res['sectors'] ?? []));
            // Filet de sécurité : si l'IA n'a rien renvoyé de valide, tag local.
            if (empty($res['sectors'])) {
                $res['sectors'] = \App\Support\SectorClassifier::classify($tender['title'] ?? null);
            }

            return $res;
        });
    }

    /** Analyse d'un besoin artisan (matching inverse). */
    public function summarizeArtisanNeed(array $need): array
    {
        $cacheKey = 'ai_need_'.md5(json_encode($need));

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($need) {
            $prompt = "Tu es l'assistant IA d'AlerteMarché. Analyse ce besoin de main d'œuvre publié par une entreprise et "
                ."produis un résumé EN FRANÇAIS (2 à 4 phrases), puis identifie le domaine métier principal (ex : Maçonnerie, Électricité, Plomberie, Menuiserie, Peinture, Soudure).\n\n"
                ."- Domaine : {$need['trade']}\n"
                ."- Employeur : ".($need['employer_name'] ?? 'Entrepreneur privé')."\n"
                ."- Besoin : ".($need['people_needed'] ?? '')."\n"
                ."- Localité : {$need['locality']} ({$need['country']})\n"
                ."- Description : ".($need['description'] ?? '')."\n\n"
                ."Réponds STRICTEMENT en JSON : {\"summary\": \"...\", \"trade\": \"...\"}";

            return $this->askJson($prompt) ?? [
                'summary' => $need['trade'].' à '.$need['locality'].'.',
                'trade' => $need['trade'],
            ];
        });
    }

    /**
     * Traduit un titre en français (fidèle, professionnel).
     * Utilisé pour le backfill des avis existants (ex. UNGM en anglais).
     * Si le titre est déjà en français, il est renvoyé tel quel.
     */
    public function translateTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return $title;
        }

        $cacheKey = 'ai_title_fr_'.md5($title);

        return Cache::remember($cacheKey, now()->addDays(90), function () use ($title) {
            $prompt = "Traduis ce titre d'appel d'offres EN FRANÇAIS de façon fidèle et professionnelle. "
                ."Si le titre est déjà en français, recopie-le tel quel. "
                ."Conserve les sigles d'organisations (UNICEF, PNUD, IOM, HCR, OMS...) et les codes de référence. "
                ."Réponds STRICTEMENT en JSON : {\"title_fr\": \"...\"}\n\nTitre : {$title}";

            $res = $this->askJson($prompt);
            $out = trim((string) ($res['title_fr'] ?? ''));

            return $out !== '' ? $out : $title;
        });
    }

    /** Score de pertinence (0-100) entre une opportunité et un profil. */
    public function relevanceScore(string $opportunity, string $profile): float
    {
        $prompt = "Évalue de 0 à 100 la pertinence entre cette opportunité et ce profil d'abonné. "
            ."Réponds STRICTEMENT en JSON : {\"score\": <nombre>}.\n\nOpportunité : {$opportunity}\n\nProfil : {$profile}";

        $res = $this->askJson($prompt);

        return isset($res['score']) ? (float) $res['score'] : 60.0;
    }

    /** Appel bas niveau — renvoie un tableau décodé depuis le JSON du modèle. */
    /**
     * OCR/vision : télécharge le PDF d'un avis (dao_url) et demande à GPT-4o
     * d'en extraire la DATE LIMITE de dépôt et le MONTANT estimatif.
     *
     * Fonctionne y compris pour les PDF SCANNÉS (images), grâce à la vision du
     * modèle. Résultat mis en cache 30 jours par URL pour maîtriser les coûts.
     *
     * @return array{deadline: ?string, estimated_amount: ?string}|null
     *         'deadline' au format 'YYYY-MM-DD' (ou null), 'estimated_amount'
     *         chaîne avec devise (ou null). null si OCR indisponible/échec.
     */
    public function extractPdfMeta(?string $daoUrl): ?array
    {
        if (! config('services.openai.ocr_enabled', true)) {
            return null;
        }
        if (empty($this->key)) {
            Log::warning('OpenAI OCR : clé API absente.');

            return null;
        }
        if (empty($daoUrl) || ! preg_match('#^https?://#i', $daoUrl)) {
            return null;
        }

        $cacheKey = 'ocr_pdf_'.md5($daoUrl);

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($daoUrl) {
            // 1) Téléchargement du PDF (borné en taille).
            $maxBytes = max(1, (int) config('services.openai.ocr_max_mb', 12)) * 1024 * 1024;
            try {
                $dl = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; AlerteMarcheBot/1.0)',
                ])->timeout(60)->withOptions(['verify' => false])->get($daoUrl);
            } catch (\Throwable $e) {
                Log::warning('OpenAI OCR : téléchargement PDF échoué', ['url' => $daoUrl, 'err' => $e->getMessage()]);

                return null;
            }
            if ($dl->failed()) {
                return null;
            }
            $bytes = $dl->body();
            if ($bytes === '' || strlen($bytes) > $maxBytes) {
                return null;
            }
            // Vérifie l'entête PDF (%PDF) — on n'envoie pas des pages HTML.
            if (! str_starts_with(ltrim($bytes), '%PDF')) {
                return null;
            }

            // 2) Appel GPT-4o Vision avec le PDF en pièce jointe (base64).
            $dataUri = 'data:application/pdf;base64,'.base64_encode($bytes);
            $prompt = "Tu analyses un AVIS D'APPEL D'OFFRES (document PDF, éventuellement scanné).\n"
                ."Extrais uniquement :\n"
                ."1. La DATE LIMITE de dépôt/remise des offres (l'échéance de soumission). "
                ."Format STRICT 'AAAA-MM-JJ'. Si absente ou illisible, mets null.\n"
                ."2. Le MONTANT ESTIMATIF / budget prévisionnel du marché, avec sa devise "
                ."(ex : '150 000 000 FCFA'). S'il n'est pas explicitement indiqué, mets null "
                ."(n'invente jamais un montant).\n"
                ."Réponds STRICTEMENT en JSON : {\"deadline\": \"AAAA-MM-JJ\"|null, \"estimated_amount\": \"...\"|null}";

            try {
                $response = Http::withToken($this->key)
                    ->timeout(120)
                    ->post($this->baseUrl.'/chat/completions', [
                        'model' => config('services.openai.ocr_model', $this->model),
                        'messages' => [
                            ['role' => 'system', 'content' => 'Tu réponds uniquement en JSON valide, en français.'],
                            ['role' => 'user', 'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                ['type' => 'file', 'file' => ['filename' => 'avis.pdf', 'file_data' => $dataUri]],
                            ]],
                        ],
                        'response_format' => ['type' => 'json_object'],
                        'temperature' => 0.1,
                    ]);
            } catch (\Throwable $e) {
                Log::error('OpenAI OCR exception', ['message' => $e->getMessage()]);

                return null;
            }

            if ($response->failed()) {
                Log::error('OpenAI OCR erreur HTTP', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);

                return null;
            }

            $content = $response->json('choices.0.message.content');
            $data = $content ? json_decode($content, true) : null;
            if (! is_array($data)) {
                return null;
            }

            // Normalisation défensive.
            $deadline = null;
            if (! empty($data['deadline']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $data['deadline']))) {
                $deadline = trim((string) $data['deadline']);
            }
            $amount = null;
            if (! empty($data['estimated_amount'])) {
                $amount = trim((string) $data['estimated_amount']);
                if (mb_strlen($amount) > 120 || preg_match('/^(null|non communiqu|n\/a|aucun)/i', $amount)) {
                    $amount = null;
                }
            }

            return ['deadline' => $deadline, 'estimated_amount' => $amount];
        });
    }

    protected function askJson(string $prompt): ?array
    {
        if (empty($this->key)) {
            Log::warning('OpenAI: clé API absente, fallback local.');

            return null;
        }

        try {
            $response = Http::withToken($this->key)
                ->timeout(45)
                ->post($this->baseUrl.'/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu réponds uniquement en JSON valide, en français.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.3,
                ]);

            if ($response->failed()) {
                Log::error('OpenAI erreur HTTP', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $content = $response->json('choices.0.message.content');

            return $content ? json_decode($content, true) : null;
        } catch (\Throwable $e) {
            Log::error('OpenAI exception', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
