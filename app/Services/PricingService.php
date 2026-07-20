<?php

namespace App\Services;

/**
 * Calcul de la tarification multi-pays (règle V7).
 * Total = Tarif de base × Nombre de pays sélectionnés.
 * Remise de lancement -50% appliquée par défaut.
 */
class PricingService
{
    /** Tarif de base normal par profil (FCFA / mois / pays). */
    public function basePrice(string $profile): int
    {
        $prices = (array) config('alertemarche.prices');

        return match ($profile) {
            'artisan' => (int) ($prices['artisan'] ?? 10000),
            'prestataire' => (int) ($prices['prestataire'] ?? 50000),
            'admin_public' => (int) ($prices['admin'] ?? 150000),
            'ong' => (int) ($prices['ong'] ?? 150000),
            default => (int) ($prices['prestataire'] ?? 50000),
        };
    }

    /** Calcul complet du devis. */
    public function quote(string $profile, int $countryCount, bool $promo = true): array
    {
        $countryCount = max(1, min(3, $countryCount));
        $base = $this->basePrice($profile);
        $discount = (int) config('alertemarche.launch_discount_percent', 50);

        $normalTotal = $base * $countryCount;
        $promoUnit = $promo ? (int) round($base * (100 - $discount) / 100) : $base;
        $promoTotal = $promoUnit * $countryCount;

        return [
            'profile' => $profile,
            'country_count' => $countryCount,
            'base_price' => $base,
            'promo_unit_price' => $promoUnit,
            'normal_total' => $normalTotal,
            'promo_total' => $promoTotal,
            'savings' => $normalTotal - $promoTotal,
            'discount_percent' => $discount,
            'currency' => 'FCFA',
        ];
    }
}
