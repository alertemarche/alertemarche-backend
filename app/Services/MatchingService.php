<?php

namespace App\Services;

use App\Models\ArtisanNeed;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Moteur de matching tricouche (cahier des charges V7) :
 *  1. Matching direct Prestataires    : appel d'offres → prestataires (secteur + pays)
 *  2. Matching inverse Artisans        : besoin entreprise → artisans (métier + localité + pays)
 *  3. Matching inverse Sourcing        : besoin Admin/ONG → prestataires (secteur + pays)
 */
class MatchingService
{
    /** Prestataires (et admin/ong) correspondant à un appel d'offres. */
    public function usersForTender(Tender $tender): Collection
    {
        $sectors = (array) ($tender->sectors ?? []);

        return User::query()
            ->whereIn('profile_type', ['prestataire', 'admin_public', 'ong'])
            ->where('is_suspended', false)
            ->where(function ($q) use ($tender) {
                // pays principal ou pays d'un abonnement actif
                $q->where('primary_country', $tender->country)
                    ->orWhereHas('subscriptions', function ($s) use ($tender) {
                        $s->where('status', 'active')
                            ->whereJsonContains('countries', $tender->country);
                    });
            })
            ->get()
            ->filter(function (User $user) use ($sectors) {
                if (empty($sectors) || empty($user->sectors)) {
                    return true; // pas de filtre sectoriel → on notifie
                }

                return count(array_intersect($sectors, (array) $user->sectors)) > 0;
            })
            ->values();
    }

    /** Artisans correspondant à un besoin publié (matching inverse). */
    public function artisansForNeed(ArtisanNeed $need): Collection
    {
        $trade = mb_strtolower($need->trade);
        $locality = mb_strtolower((string) $need->locality);
        $region = mb_strtolower((string) $need->region);

        return User::query()
            ->where('profile_type', 'artisan')
            ->where('is_suspended', false)
            ->where(function ($q) use ($need) {
                $q->where('primary_country', $need->country)
                    ->orWhereHas('subscriptions', function ($s) use ($need) {
                        $s->where('status', 'active')
                            ->whereJsonContains('countries', $need->country);
                    });
            })
            ->get()
            ->filter(function (User $user) use ($trade, $locality, $region) {
                $userTrade = mb_strtolower((string) $user->artisan_trade);
                $userLoc = mb_strtolower((string) $user->artisan_locality);

                $tradeMatch = $userTrade === '' || str_contains($userTrade, $trade) || str_contains($trade, $userTrade);
                $locMatch = $userLoc === ''
                    || str_contains($userLoc, $locality)
                    || ($region !== '' && str_contains($userLoc, $region));

                return $tradeMatch && $locMatch;
            })
            ->values();
    }
}
