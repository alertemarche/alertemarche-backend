<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Alimente la table `sectors` à partir du référentiel canonique
 * config/sectors.php (SOURCE DE VÉRITÉ UNIQUE, 21 secteurs data-réels du Bénin).
 *
 * On garde volontairement les MÊMES `name` que ceux stockés dans users.sectors
 * (JSON) afin que le ciblage « par secteur » de la newsletter corresponde
 * réellement aux profils des abonnés. On enrichit chaque secteur d'un slug et
 * d'une icône (emoji) pour l'affichage back-office.
 *
 * Les métiers artisans (type = metier) sont conservés pour compatibilité.
 */
class SectorSeeder extends Seeder
{
    /** Icônes par code de secteur (référentiel config/sectors.php). */
    private const ICONS = [
        'btp' => '🏗️', 'informatique' => '💻', 'telecom' => '📡', 'sante' => '🏥',
        'agriculture' => '🌾', 'energie' => '⚡', 'eau' => '💧', 'transport' => '🚚',
        'education' => '🎓', 'environnement' => '♻️', 'finance' => '🏦',
        'fournitures' => '📦', 'communication' => '📣', 'conseil' => '📊',
        'securite' => '🛡️', 'nettoyage' => '🧹', 'hotellerie' => '🏨',
        'juridique' => '⚖️', 'textile' => '👔', 'mines' => '⛏️', 'culture' => '🎭',
    ];

    public function run(): void
    {
        // 1) Secteurs canoniques issus de config/sectors.php
        foreach ((array) config('sectors.list', []) as $s) {
            $code = $s['code'] ?? null;
            $name = $s['name'] ?? null;
            if (! $code || ! $name) {
                continue;
            }
            Sector::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => 'secteur',
                    'slug' => Str::slug($code),
                    'icon' => self::ICONS[$code] ?? '🏷️',
                ]
            );
        }

        // 2) Métiers artisans (conservés pour compatibilité).
        $metiers = [
            'maconnerie' => 'Maçonnerie', 'electricite' => 'Électricité', 'plomberie' => 'Plomberie',
            'menuiserie' => 'Menuiserie', 'peinture' => 'Peinture', 'soudure' => 'Soudure',
            'carrelage' => 'Carrelage', 'climatisation' => 'Climatisation & Froid',
        ];
        foreach ($metiers as $code => $name) {
            Sector::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => 'metier', 'slug' => Str::slug($code), 'icon' => '🔧']
            );
        }
    }
}
