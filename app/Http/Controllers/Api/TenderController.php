<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenderController extends Controller
{
    /** Listing public des appels d'offres avec filtres pays/secteur/type. */
    public function index(Request $request): JsonResponse
    {
        $query = Tender::query()->where('ai_processed', true);

        // Tri serveur (s'applique à TOUS les résultats, pas seulement à la page
        // affichée) — aligné sur les options du frontend / des concurrents :
        //   • recent   : les plus récemment publiés/collectés (défaut)
        //   • deadline : échéance de soumission la plus proche d'abord
        //   • budget   : montant estimé le plus élevé d'abord
        switch ((string) $request->string('sort')) {
            case 'deadline':
                // Les avis sans deadline passent en dernier (NULLS LAST).
                $query->orderByRaw('deadline IS NULL, deadline ASC')
                      ->orderByDesc('collected_at');
                break;
            case 'budget':
                // estimated_amount est un texte libre (ex. « 150 000 000 FCFA » ou
                // « Non communiqué ») : on extrait les chiffres et on trie en
                // numérique décroissant, les montants absents/nuls en dernier.
                $query->orderByRaw("NULLIF(regexp_replace(COALESCE(estimated_amount, ''), '[^0-9]', '', 'g'), '')::numeric DESC NULLS LAST")
                      ->orderByDesc('collected_at');
                break;
            default: // recent
                $query->orderByRaw('COALESCE(publication_date, collected_at::date) DESC')
                      ->orderByDesc('collected_at');
        }

        if ($request->filled('country')) {
            $query->where('country', $request->string('country'));
        }

        // Filtre « actifs et à venir » (activé par défaut sauf si active=false) :
        // - Exclut les AO avec deadline passée
        // - Exclut les AO sans deadline créés il y a > 90 jours (cas des sources
        //   inactives comme Plan International qui polluaient le site)
        // - Les plans de passation et avis généraux sont toujours gardés (types spécifiques)
        if (!$request->has('active') || $request->boolean('active')) {
            $query->where(function ($q) use ($request) {
                // Garder si deadline future
                $q->where('deadline', '>=', now()->startOfDay())
                  // OU si pas de deadline MAIS créé récemment (< 90 jours)
                  ->orWhere(function ($sub) {
                      $sub->whereNull('deadline')
                          ->where('created_at', '>=', now()->subDays(90));
                  })
                  // OU si c'est un plan de passation / avis général (toujours pertinent)
                  ->orWhereIn('type', ['plan_passation', 'avis_general']);
            });
        }

        // Filtre par type de procédure de passation (sous-catégories « Appels
        // d'offres publics » : cotation, drp, aaon, aaoi, ami, autre).
        $hasProcedure = $request->filled('procedure_type');
        if ($hasProcedure) {
            $query->where('procedure_type', $request->string('procedure_type'));
        }

        if ($request->filled('type')) {
            // Filtre explicite : le frontend peut cibler un ou plusieurs types
            // (public, prive, aac, avis_general, plan_passation), séparés par
            // des virgules pour une recherche unifiée multi-catégories.
            $types = array_values(array_filter(array_map('trim', explode(',', (string) $request->string('type')))));
            if (count($types) > 1) {
                $query->whereIn('type', $types);
            } else {
                $query->where('type', $types[0] ?? (string) $request->string('type'));
            }
        } elseif ($hasProcedure) {
            // Filtre procédure sans type explicite : on couvre les marchés
            // publics actifs/planifiés (avis formels + plans de passation),
            // c'est là que vivent les procédures de passation.
            $query->whereIn('type', ['public', 'aac', 'plan_passation']);
        } else {
            // Sans filtre explicite : on retourne SEULEMENT les marchés actifs
            // (public, privé, aac). Les documents de planification (plan_passation,
            // avis_general) sont exclus par défaut et accessibles via ?type=plan_passation
            $query->whereIn('type', ['public', 'prive', 'aac']);
        }
        if ($request->filled('sector')) {
            $query->whereJsonContains('sectors', (string) $request->string('sector'));
        }
        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(fn ($sub) => $sub->where('title', 'ilike', $q)
                ->orWhere('title_fr', 'ilike', $q)
                ->orWhere('institution', 'ilike', $q)
                ->orWhere('reference', 'ilike', $q));
        }

        $paginator = $query->paginate(min(500, (int) $request->integer('per_page', 15)));

        // Paywall freemium : les champs sensibles (budget, date limite, référence,
        // liens officiels) ne sont visibles que pour les abonnés payants actifs.
        $unlocked = $this->userHasAccess();

        // Affichage francisé : on expose le titre français quand il est disponible,
        // tout en conservant le titre d'origine dans `title_original` (traçabilité).
        $paginator->getCollection()->transform(function (Tender $tender) use ($unlocked) {
            return $this->applyPaywall($this->frenchify($tender), $unlocked);
        });

        return response()->json($paginator);
    }

    public function show(Tender $tender): JsonResponse
    {
        $unlocked = $this->userHasAccess();

        return response()->json($this->applyPaywall($this->frenchify($tender), $unlocked));
    }

    /**
     * Détermine si l'utilisateur courant a accès aux données complètes.
     * Vrai uniquement pour un utilisateur authentifié disposant d'un
     * abonnement payant actif. Les visiteurs anonymes et les inscrits sans
     * abonnement (plan gratuit) reçoivent des avis « verrouillés ».
     */
    protected function userHasAccess(): bool
    {
        $user = auth('sanctum')->user();

        return $user !== null && $user->hasActiveSubscription();
    }

    /**
     * Applique la logique de paywall à un avis.
     * - `is_locked` = false et données complètes pour les abonnés actifs.
     * - `is_locked` = true et champs sensibles masqués (null) sinon.
     */
    protected function applyPaywall(Tender $tender, bool $unlocked): Tender
    {
        if ($unlocked) {
            $tender->setAttribute('is_locked', false);

            return $tender;
        }

        $tender->setAttribute('estimated_amount', null);
        $tender->setAttribute('deadline', null);
        $tender->setAttribute('reference', null);
        $tender->setAttribute('source_url', null);
        $tender->setAttribute('dao_url', null);

        // Masque l'identité de l'acheteur pour empêcher un visiteur non abonné
        // de retrouver l'avis directement sur le portail officiel.
        $tender->setAttribute('institution', null);
        $tender->setAttribute('external_id', null);
        $tender->setAttribute('ai_summary', null);

        // Le nom de l'acheteur figure souvent dans le titre lui-même
        // (« … au profit de la Police républicaine », « … du CHD Zou », « … - PARAE »).
        // On sert le teaser calculé au scraping (teaser_title) ; à défaut (avis
        // anciens sans teaser en base) on le calcule à la volée sur le titre affiché.
        $stored = trim((string) $tender->getAttribute('teaser_title'));
        $teaser = $stored !== ''
            ? $stored
            : Tender::teaserTitle((string) $tender->getAttribute('title'));
        $tender->setAttribute('title', $teaser);
        $tender->setAttribute('title_fr', $teaser);
        $tender->setAttribute('title_original', null);
        $tender->setAttribute('teaser_title', null);

        $tender->setAttribute('is_locked', true);

        return $tender;
    }

    /** Remplace le titre affiché par sa traduction française si disponible. */
    protected function frenchify(Tender $tender): Tender
    {
        if (! empty($tender->title_fr) && $tender->title_fr !== $tender->title) {
            $tender->setAttribute('title_original', $tender->title);
            $tender->setAttribute('title', $tender->title_fr);
        }

        return $tender;
    }
}
