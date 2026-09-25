<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute deux colonnes à la table `tenders` pour la saisie MANUELLE de marchés
 * depuis le back-office (photos d'avis affichés en mairie/préfecture,
 * pré-remplies par l'IA GPT-4o Vision) :
 *
 *  - `is_manual`  : distingue les marchés saisis à la main de ceux collectés
 *                   automatiquement par les robots (scrapers).
 *  - `image_url`  : URL/chemin de l'image source de l'avis (photo uploadée),
 *                   à titre de traçabilité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('ocr_processed');
            $table->string('image_url')->nullable()->after('is_manual');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn(['is_manual', 'image_url']);
        });
    }
};
