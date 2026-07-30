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
        // Ne matche pas les tenders expirés (deadline dépassée).
        // Les tenders sans deadline (null) restent matchables indéfiniment.
        if ($tender->deadline !== null && $tender->deadline < now()) {
            return collect([]);
        }

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
            ->filter(function (User $user) use ($tender, $sectors) {
                $userSectors = (array) $user->sectors;
                $userKeywords = (array) $user->keywords;

                // Aucun critère défini par l'utilisateur → on notifie (fallback).
                if (empty($userSectors) && empty($userKeywords)) {
                    return true;
                }

                // 1) Correspondance sectorielle (intersection des noms canoniques).
                if (! empty($userSectors) && ! empty($sectors)
                    && count(array_intersect($sectors, $userSectors)) > 0) {
                    return true;
                }

                // 2) Correspondance par mot-clé personnalisé dans l'intitulé.
                if (! empty($userKeywords)) {
                    $haystack = \App\Support\SectorClassifier::normalize(
                        ($tender->title_fr ?: $tender->title).' '.$tender->institution
                    );
                    foreach ($userKeywords as $kw) {
                        $kw = \App\Support\SectorClassifier::normalize($kw);
                        if ($kw !== '' && str_contains($haystack, $kw)) {
                            return true;
                        }
                    }
                }

                return false;
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
