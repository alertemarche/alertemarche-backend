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
            $prompt = "Tu es l'assistant IA d'AlerteMarché, plateforme de veille des appels d'offres en Afrique de l'Ouest.\n"
                ."À partir des métadonnées suivantes :\n"
                ."1. Traduis le titre EN FRANÇAIS de façon fidèle et professionnelle (si le titre est déjà en français, recopie-le tel quel ; conserve les sigles d'organisations et les codes de référence).\n"
                ."2. Produis un résumé PROFESSIONNEL, clair et concis EN FRANÇAIS (4 à 6 phrases max).\n"
                ."3. Identifie les secteurs d'activité concernés parmi : BTP, Informatique, Santé, Agriculture, Énergie, Transport, Éducation, Environnement, Finance, Fournitures.\n\n"
                ."Métadonnées :\n"
                ."- Objet : {$tender['title']}\n"
                ."- Institution : {$tender['institution']}\n"
                ."- Montant estimé : ".($tender['estimated_amount'] ?? 'Non communiqué')."\n"
                ."- Date limite : ".($tender['deadline'] ?? 'Non communiquée')."\n"
                ."- Pays : {$tender['country']}\n\n"
                ."Réponds STRICTEMENT en JSON : {\"title_fr\": \"...\", \"summary\": \"...\", \"sectors\": [\"...\"]}";

            return $this->askJson($prompt) ?? [
                'title_fr' => $tender['title'],
                'summary' => $tender['title'].' — '.$tender['institution'].'.',
                'sectors' => [],
            ];
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
