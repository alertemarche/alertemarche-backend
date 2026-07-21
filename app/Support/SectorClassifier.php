<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Classifieur sectoriel centralisé — s'appuie sur config/sectors.php
 * (source de vérité unique). Utilisé par le taggage local des marchés,
 * par la route /sectors et par le matching des notifications.
 */
class SectorClassifier
{
    /** Liste brute du référentiel (code, name, keywords). */
    public static function all(): array
    {
        return config('sectors.list', []);
    }

    /** Liste allégée {code, name} pour l'API / le frontend. */
    public static function options(): array
    {
        return array_map(
            fn ($s) => ['code' => $s['code'], 'name' => $s['name']],
            self::all()
        );
    }

    /** Noms canoniques valides (valeurs stockées et comparées). */
    public static function names(): array
    {
        return array_map(fn ($s) => $s['name'], self::all());
    }

    /** Normalisation : minuscules + sans accents. */
    public static function normalize(?string $text): string
    {
        return Str::ascii(mb_strtolower(trim((string) $text)));
    }

    /**
     * Déduit les secteurs (noms canoniques) d'un texte libre par mots-clés.
     * Retourne un tableau de "name" uniques.
     */
    public static function classify(?string ...$parts): array
    {
        $haystack = self::normalize(implode(' ', array_filter($parts)));
        if ($haystack === '') {
            return [];
        }

        $found = [];
        foreach (self::all() as $sector) {
            foreach ($sector['keywords'] as $kw) {
                if ($kw !== '' && str_contains($haystack, $kw)) {
                    $found[] = $sector['name'];
                    break;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Filtre une liste de noms candidats (issus de l'IA p. ex.) en ne
     * gardant que ceux présents dans le référentiel.
     */
    public static function keepValid(array $names): array
    {
        $valid = self::names();

        return array_values(array_intersect($names, $valid));
    }
}
