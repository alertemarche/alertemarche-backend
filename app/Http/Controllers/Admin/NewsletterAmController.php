<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNewsletterJob;
use App\Models\NewsletterAm;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Back-office AlerteMarché — Newsletters & Annonces publicitaires.
 * Composition, ciblage (tous / par secteur / par pays), aperçu et envoi Brevo.
 */
class NewsletterAmController extends Controller
{
    /** Liste paginée des campagnes (les plus récentes en premier). */
    public function index(): JsonResponse
    {
        $page = NewsletterAm::query()
            ->with('sector:id,name,icon')
            ->latest()
            ->paginate(25);

        $map = fn (NewsletterAm $n) => [
            'id' => $n->id,
            'subject' => $n->subject,
            'type' => $n->type,
            'target_type' => $n->target_type,
            'target_sector_id' => $n->target_sector_id,
            'target_sector' => $n->sector?->name,
            'target_country' => $n->target_country,
            'sent_count' => (int) $n->sent_count,
            'status' => $n->status,
            'sent_at' => $n->sent_at,
            'created_at' => $n->created_at,
        ];

        return response()->json([
            'data' => collect($page->items())->map($map)->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    /** Crée un brouillon de campagne. */
    public function create(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $nl = NewsletterAm::create([
            'subject' => $data['subject'],
            'body' => $data['body'],
            'type' => $data['type'],
            'target_type' => $data['target_type'],
            'target_sector_id' => $data['target_type'] === 'by_sector' ? ($data['target_sector_id'] ?? null) : null,
            'target_country' => $data['target_type'] === 'by_country' ? ($data['target_country'] ?? null) : null,
            'status' => 'draft',
            'sent_count' => 0,
        ]);

        return response()->json([
            'message' => 'Brouillon enregistré.',
            'newsletter' => $nl,
            'target_count' => $nl->recipientsQuery()->count(),
        ], 201);
    }

    /** Renvoie le HTML de l'e-mail pour aperçu. */
    public function preview(NewsletterAm $newsletter): JsonResponse
    {
        $html = view('emails.newsletter_am', [
            'subject' => $newsletter->subject,
            'body' => $newsletter->body,
            'type' => $newsletter->type,
        ])->render();

        return response()->json(['html' => $html]);
    }

    /**
     * Compte les destinataires selon un ciblage donné (appel AJAX avant envoi).
     * Utilise une instance non persistée pour réutiliser recipientsQuery().
     */
    public function getTargetCount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', 'in:all,by_sector,by_country'],
            'target_sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'target_country' => ['nullable', 'string', 'max:5'],
        ]);

        $probe = new NewsletterAm([
            'target_type' => $data['target_type'],
            'target_sector_id' => $data['target_sector_id'] ?? null,
            'target_country' => $data['target_country'] ?? null,
        ]);
        // target_sector_id n'est pas dans $fillable via new() ? Il l'est ; on force par sûreté.
        $probe->target_type = $data['target_type'];
        $probe->target_sector_id = $data['target_sector_id'] ?? null;
        $probe->target_country = $data['target_country'] ?? null;

        return response()->json(['count' => $probe->recipientsQuery()->count()]);
    }

    /** Dispatche l'envoi de la campagne dans la file d'attente. */
    public function send(NewsletterAm $newsletter): JsonResponse
    {
        if ($newsletter->status === 'sent') {
            return response()->json(['message' => 'Cette campagne a déjà été envoyée.'], 422);
        }
        if ($newsletter->status === 'sending') {
            return response()->json(['message' => 'Cette campagne est déjà en cours d\'envoi.'], 422);
        }

        $count = $newsletter->recipientsQuery()->count();
        $newsletter->update(['status' => 'sending']);

        SendNewsletterJob::dispatch($newsletter->id)->onQueue('default');

        return response()->json([
            'message' => "Envoi lancé pour {$count} destinataire(s).",
            'target_count' => $count,
        ]);
    }

    /** Supprime un brouillon (impossible sur une campagne envoyée / en cours). */
    public function destroy(NewsletterAm $newsletter): JsonResponse
    {
        if (in_array($newsletter->status, ['sent', 'sending'], true)) {
            return response()->json(['message' => 'Impossible de supprimer une campagne envoyée ou en cours.'], 422);
        }

        $newsletter->delete();

        return response()->json(['message' => 'Brouillon supprimé.']);
    }

    /**
     * Liste des secteurs (référentiel canonique) avec le nombre d'abonnés
     * de chaque secteur (déclaré via users.sectors OU pivot user_sectors).
     */
    public function sectors(): JsonResponse
    {
        $sectors = Sector::query()
            ->where('type', 'secteur')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'slug', 'icon']);

        $sectors->transform(function (Sector $s) {
            $count = User::query()
                ->where(function ($w) use ($s) {
                    $w->whereJsonContains('sectors', $s->name)
                        ->orWhereHas('sectorsPivot', fn ($p) => $p->where('sectors.id', $s->id));
                })
                ->count();

            return [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'slug' => $s->slug,
                'icon' => $s->icon ?: '🏷️',
                'subscribers_count' => $count,
            ];
        });

        return response()->json(['data' => $sectors->values()]);
    }

    /**
     * Attribue un secteur à un ou plusieurs utilisateurs (pivot user_sectors).
     * Attribution manuelle en masse depuis le back-office.
     */
    public function assignSector(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sector_id' => ['required', 'integer', 'exists:sectors,id'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $sector = Sector::findOrFail($data['sector_id']);
        // syncWithoutDetaching : ajoute sans retirer les rattachements existants.
        $sector->users()->syncWithoutDetaching($data['user_ids']);

        return response()->json([
            'message' => count($data['user_ids']).' utilisateur(s) rattaché(s) au secteur « '.$sector->name.' ».',
        ]);
    }

    /** Validation commune à create(). */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['required', 'in:newsletter,pub'],
            'target_type' => ['required', 'in:all,by_sector,by_country'],
            'target_sector_id' => ['nullable', 'integer', 'exists:sectors,id', 'required_if:target_type,by_sector'],
            'target_country' => ['nullable', 'string', 'max:5', 'required_if:target_type,by_country'],
        ]);
    }
}
