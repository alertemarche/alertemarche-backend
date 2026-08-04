<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute une colonne `teaser_title` : version tronquée du titre (objet du
 * marché conservé, identité de l'acheteur masquée en fin) servie aux visiteurs
 * NON abonnés. Calculée automatiquement à l'ingestion (juste après le scraping)
 * et recalculée après la traduction française (title_fr) par l'IA.
 * Le titre complet (`title` / `title_fr`) reste intact pour les abonnés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->string('teaser_title', 512)->nullable()->after('title_fr');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn('teaser_title');
        });
    }
};
