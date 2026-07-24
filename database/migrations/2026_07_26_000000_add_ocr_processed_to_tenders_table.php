<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute un indicateur `ocr_processed` à la table `tenders`.
 *
 * Marque qu'une tentative d'extraction OCR (lecture IA du PDF de l'avis via
 * GPT-4o Vision) a déjà été réalisée pour cet appel d'offres, afin de ne jamais
 * relancer l'OCR — coûteux — plus d'une fois par avis, même si celui-ci est
 * ré-ingéré lors d'une passe de collecte ultérieure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->boolean('ocr_processed')->default(false)->after('ai_processed');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn('ocr_processed');
        });
    }
};
