<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtisanNeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArtisanNeedController extends Controller
{
    /** Liste publique des besoins approuvés. */
    public function index(Request $request): JsonResponse
    {
        $query = ArtisanNeed::query()->where('status', 'approved')->latest();

        if ($request->filled('country')) {
            $query->where('country', $request->string('country'));
        }
        if ($request->filled('trade')) {
            $query->where('trade', 'ilike', '%'.$request->string('trade').'%');
        }

        return response()->json($query->paginate(15));
    }

    /** Publication d'un besoin (entreprises, admin, ONG) — validation éditoriale requise. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trade' => ['required', 'string', 'max:255'],
            'employer_name' => ['nullable', 'string', 'max:255'],
            'people_needed' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'locality' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['required', Rule::in(['BJ', 'TG', 'CI', 'SN'])],
            'estimated_budget' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'contact' => ['required', 'string', 'max:255'],
        ]);

        $need = ArtisanNeed::create([
            ...$data,
            'publisher_id' => $request->user()?->id,
            'status' => 'pending', // en attente de validation éditoriale
        ]);

        return response()->json([
            'message' => 'Besoin publié. Il sera diffusé après validation par notre équipe.',
            'need' => $need,
        ], 201);
    }

    /** Suivi : nombre d'artisans alertés pour un besoin publié. */
    public function responses(Request $request, ArtisanNeed $need): JsonResponse
    {
        abort_unless($need->publisher_id === $request->user()?->id, 403);

        $count = \App\Models\Alert::where('source_type', 'artisan_need')
            ->where('source_id', $need->id)->count();

        return response()->json(['need_id' => $need->id, 'artisans_alerted' => $count]);
    }
}
