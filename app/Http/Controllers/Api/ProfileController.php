<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /** Mise à jour des préférences (secteurs, localités, notifications). */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($request->user()->id)],
            'sectors' => ['nullable', 'array'],
            'sectors.*' => ['string', 'max:80'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:60'],
            'artisan_trade' => ['nullable', 'string', 'max:255'],
            'artisan_locality' => ['nullable', 'string', 'max:255'],
            'artisan_radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'primary_country' => ['nullable', 'string', 'size:2'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        // WhatsApp uniquement si abonnement payant actif
        if (array_key_exists('notify_whatsapp', $data) && $data['notify_whatsapp'] && ! $user->hasActiveSubscription()) {
            $data['notify_whatsapp'] = false;
        }

        $user->update($data);

        return response()->json(['message' => 'Profil mis à jour.', 'user' => $user->fresh()]);
    }
}
