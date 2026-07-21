<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passage au modèle d'abonnement par durée (mensuel / trimestriel /
 * semestriel / annuel). On ajoute deux colonnes et on assouplit les
 * anciennes colonnes liées au modèle par profil/pays afin de ne pas
 * casser les enregistrements existants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('plan')->nullable()->after('user_id');           // mensuel|trimestriel|semestriel|annuel
            $table->unsignedSmallInteger('duration_months')->default(1)->after('plan');
        });

        // Anciennes colonnes rendues optionnelles (modèle profil/pays déprécié).
        foreach (['profile_type', 'countries', 'base_price'] as $col) {
            try {
                DB::statement("ALTER TABLE subscriptions ALTER COLUMN {$col} DROP NOT NULL");
            } catch (\Throwable $e) {
                // Ignorer si déjà nullable ou moteur différent.
            }
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['plan', 'duration_months']);
        });
    }
};
