<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Métadonnées enrichies des avis (issues des sources officielles, ex. API
 * marches-publics.bj). Toujours des MÉTADONNÉES uniquement — le lien vers le
 * DAO (dao_url) est conservé, jamais le fichier lui-même.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('institution');      // ex. F_PEA-ICAT_106495
            $table->string('location')->nullable()->after('reference');         // lieu / dépôt / région
            $table->string('market_type')->nullable()->after('type');           // Fournitures | Travaux | Services | Prestations intellectuelles
            $table->text('dao_url')->nullable()->after('source_url');            // lien officiel du DAO (PDF) — jamais stocké
            $table->date('publication_date')->nullable()->after('deadline');    // date de publication
            $table->unsignedSmallInteger('nb_lots')->nullable()->after('publication_date'); // nombre de lots
            $table->string('external_id')->nullable()->after('dedup_hash');     // identifiant source (dosID) pour détection de modifications
            $table->index('external_id');
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropIndex(['reference']);
            $table->dropColumn(['reference', 'location', 'market_type', 'dao_url', 'publication_date', 'nb_lots', 'external_id']);
        });
    }
};
