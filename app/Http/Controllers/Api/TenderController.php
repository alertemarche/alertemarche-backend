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
        $query = Tender::query()->where('ai_processed', true)->latest('collected_at');

        if ($request->filled('country')) {
            $query->where('country', $request->string('country'));
        }
        if ($request->filled('type')) {
            // Filtre explicite : le frontend peut cibler n'importe quel type
            // (public, prive, aac, avis_general, plan_passation).
            $query->where('type', $request->string('type'));
        } else {
            // Sans filtre : on ne renvoie que les opportunités actives dans la liste
            // principale (public, prive, aac). Les documents de planification
            // (avis_general, plan_passation) sont exclus par défaut.
            $query->whereIn('type', ['public', 'prive', 'aac']);
        }
        if ($request->filled('sector')) {
            $query->whereJsonContains('sectors', (string) $request->string('sector'));
        }
        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(fn ($sub) => $sub->where('title', 'ilike', $q)->orWhere('institution', 'ilike', $q));
        }

        return response()->json($query->paginate(min(50, (int) $request->integer('per_page', 15))));
    }

    public function show(Tender $tender): JsonResponse
    {
        return response()->json($tender);
    }
}
