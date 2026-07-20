<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Étend la documentation des types d'appels d'offres acceptés par la colonne `type`.
 *
 * La colonne `type` de la table `tenders` est déjà de type string (VARCHAR) et ne
 * nécessite donc AUCUNE modification de schéma. Cette migration se contente de
 * documenter les valeurs désormais valides :
 *
 *   - public         : appels d'offres publics standards (DNCMP, status=1)
 *   - prive          : ONG et organisations internationales (UNGM : UNICEF, PNUD, OMS…)
 *   - aac            : Avis d'appel à concurrence (source publique gouvernement Bénin)
 *   - avis_general   : Avis général (source publique gouvernement Bénin)
 *   - plan_passation : Plan de passation de marchés (source publique gouvernement Bénin)
 *
 * Les types "aac", "avis_general" et "plan_passation" proviennent tous de sources
 * PUBLIQUES (gouvernement du Bénin).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ajout d'un commentaire SQL sur la colonne pour documenter les types valides
        // (PostgreSQL). Aucune modification de schéma nécessaire : `type` est déjà string.
        try {
            DB::statement("COMMENT ON COLUMN tenders.type IS 'Type d''avis : public | prive | aac | avis_general | plan_passation'");
        } catch (\Throwable $e) {
            // Silencieux : le commentaire est purement documentaire et dépend du SGBD.
        }
    }

    public function down(): void
    {
        try {
            DB::statement('COMMENT ON COLUMN tenders.type IS NULL');
        } catch (\Throwable $e) {
            // Silencieux.
        }
    }
};
