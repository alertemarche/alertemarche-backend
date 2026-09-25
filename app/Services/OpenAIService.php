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
                $status = $response->status();
                Log::error('OpenAI OCR erreur HTTP', ['status' => $status, 'body' => mb_substr($response->body(), 0, 300)]);

                // Alerte crédits épuisés (erreur 429 ou message "insufficient_quota")
                if ($status === 429 || str_contains((string) $response->body(), 'insufficient_quota')) {
                    $this->sendCreditAlert();
                }

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

    /**
     * Extraction VISION d'un avis de marché à partir d'une PHOTO (back-office).
     *
     * L'admin photographie un Avis de Demande de Renseignements et de Prix
     * (ADRP) affiché sur un panneau officiel (mairie/préfecture). GPT-4o Vision
     * lit l'image et renvoie un JSON structuré pré-remplissant le formulaire de
     * publication manuelle. L'admin vérifie/corrige puis publie en un clic.
     *
     * @param  string  $dataUri  Image encodée « data:image/jpeg;base64,... »
     * @return array|null  Champs extraits (voir prompt), ou null si échec.
     */
    public function extractTenderFromImage(string $dataUri): ?array
    {
        if (empty($this->key)) {
            Log::warning('OpenAI Vision (photo marché) : clé API absente.');

            return null;
        }
        if (! preg_match('#^data:image/[a-zA-Z0-9.+-]+;base64,#', $dataUri)) {
            Log::warning('OpenAI Vision (photo marché) : data URI image invalide.');

            return null;
        }

        $prompt = "Tu es un expert en marchés publics d'Afrique de l'Ouest (Bénin, Togo, Côte d'Ivoire, Sénégal, Burkina Faso). "
            ."Analyse cette image d'un Avis de marché public (souvent un Avis de Demande de Renseignements et de Prix — ADRP) affiché sur un panneau officiel.\n\n"
            ."Extrais les informations suivantes et réponds UNIQUEMENT avec un JSON valide (aucun texte avant ou après) :\n"
            ."{\n"
            ."  \"title\": \"objet complet du marché\",\n"
            ."  \"institution\": \"nom de l'autorité contractante\",\n"
            ."  \"reference\": \"référence SIGMAP (ex: T_EMAA_124187) ou null\",\n"
            ."  \"avis_number\": \"numéro de l'avis (ex: 101/MDN/PRMP/SP-PRMP/SA) ou null\",\n"
            ."  \"market_type\": \"travaux|fournitures|services|drp\",\n"
            ."  \"procedure_type\": \"drp|cotation|aoo|aor|gre_a_gre\",\n"
            ."  \"publication_date\": \"AAAA-MM-JJ ou null si non visible\",\n"
            ."  \"deadline\": \"AAAA-MM-JJ ou null si non visible\",\n"
            ."  \"estimated_amount\": montant_en_nombre_ou_null,\n"
            ."  \"location\": \"lieu d'exécution ou null\",\n"
            ."  \"country\": \"bj\",\n"
            ."  \"type\": \"public\",\n"
            ."  \"source_name\": \"ADRP Manuel\",\n"
            ."  \"description\": \"description courte de ce qui est demandé\"\n"
            ."}\n\n"
            ."Règles :\n"
            ."- Si un champ n'est pas lisible ou absent, mets null. Ne devine JAMAIS.\n"
            ."- Le type de marché se déduit de la référence SIGMAP : T_ = travaux, F_ = fournitures, S_ = services.\n"
            ."- market_type = \"drp\" indique une DRP (petite commande < 20M FCFA).\n"
            ."- estimated_amount doit être un NOMBRE entier (sans espaces ni devise), ou null.\n"
            ."- country : code pays à 2 lettres en minuscules (bj, tg, ci, sn, bf). Bénin = bj par défaut.";

        try {
            $response = Http::withToken($this->key)
                ->timeout(120)
                ->post($this->baseUrl.'/chat/completions', [
                    'model' => config('services.openai.ocr_model', $this->model),
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu réponds uniquement en JSON valide, en français.'],
                        ['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                        ]],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens' => 1000,
                    'temperature' => 0.1,
                ]);
        } catch (\Throwable $e) {
            Log::error('OpenAI Vision (photo marché) exception', ['message' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            $status = $response->status();
            Log::error('OpenAI Vision (photo marché) erreur HTTP', ['status' => $status, 'body' => mb_substr($response->body(), 0, 300)]);
            if ($status === 429 || str_contains((string) $response->body(), 'insufficient_quota')) {
                $this->sendCreditAlert();
            }

            return null;
        }

        $content = $response->json('choices.0.message.content');
        $data = $content ? json_decode($content, true) : null;

        return is_array($data) ? $data : null;
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
                $status = $response->status();
                Log::error('OpenAI erreur HTTP', ['status' => $status, 'body' => $response->body()]);

                // Alerte crédits épuisés (erreur 429 ou message "insufficient_quota")
                if ($status === 429 || str_contains((string) $response->body(), 'insufficient_quota')) {
                    $this->sendCreditAlert();
                }

                return null;
            }

            $content = $response->json('choices.0.message.content');

            return $content ? json_decode($content, true) : null;
        } catch (\Throwable $e) {
            Log::error('OpenAI exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Envoie une alerte email quand les crédits OpenAI sont épuisés.
     * Throttling : 1 mail maximum par 24h pour éviter le spam.
     */
    protected function sendCreditAlert(): void
    {
        $cacheKey = 'openai_credit_alert_sent';

        // Si déjà envoyé dans les 24h, ne pas renvoyer
        if (Cache::has($cacheKey)) {
            return;
        }

        try {
            $brevo = app(BrevoService::class);
            $subject = '🚨 AlerteMarché — Crédits OpenAI épuisés';
            $body = "<h2 style='color:#dc2626;'>⚠️ Crédits OpenAI épuisés</h2>"
                ."<p>Les crédits OpenAI sont épuisés (erreur 429 détectée sur l'API).</p>"
                ."<p><strong>Impact immédiat :</strong> Les nouveaux marchés collectés ne peuvent plus être traduits, "
                ."résumés et classés par secteur. Ils restent en attente dans la base de données.</p>"
                ."<h3>Action requise :</h3>"
                ."<ol>"
                ."<li>Connectez-vous sur <a href='https://platform.openai.com/account/billing' style='color:#1a7f5a;'>https://platform.openai.com/account/billing</a></li>"
                ."<li>Ajoutez des crédits (généralement 10-20\$ suffisent pour plusieurs mois)</li>"
                ."<li>Les marchés en attente seront automatiquement traités lors du prochain passage du worker</li>"
                ."</ol>"
                ."<p style='color:#666;font-size:14px;margin-top:20px;'>Cette alerte ne sera pas renvoyée avant 24h pour éviter le spam.</p>"
                ."<p style='color:#666;font-size:14px;'>— Système de monitoring AlerteMarché</p>";

            // Envoi à l'administrateur (paramètre name ajouté pour correspondre à la signature)
            $adminEmail = (string) config('alertemarche.admin_email', 'info@alertemarche.com');
            $sent = $brevo->sendAlert($adminEmail, 'Administrateur AlerteMarché', $subject, $body);

            if ($sent) {
                // Marquer comme envoyé pour 24h
                Cache::put($cacheKey, true, now()->addDay());
                Log::info('Alerte crédits OpenAI envoyée', ['recipient' => $adminEmail]);
            }
        } catch (\Throwable $e) {
            Log::error('Échec envoi alerte crédits OpenAI', ['error' => $e->getMessage()]);
        }
    }
}
