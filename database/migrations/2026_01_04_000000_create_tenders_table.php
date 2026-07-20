<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenders', function (Blueprint $table) {
            $table->id();
            // Métadonnées uniquement — AUCUN fichier DAO stocké
            $table->string('title');                    // objet du marché
            $table->string('institution');              // institution émettrice
            $table->string('estimated_amount')->nullable(); // montant estimé ou "Non communiqué"
            $table->date('deadline')->nullable();       // date limite de soumission
            $table->string('country', 2);               // BJ/TG/CI
            $table->string('type')->default('public');  // public | prive
            $table->string('source_name')->nullable();  // ARMP Bénin, DNCMP...
            $table->text('source_url');                 // lien officiel
            $table->json('sectors')->nullable();        // secteurs classifiés par IA
            // IA
            $table->text('ai_summary')->nullable();     // résumé structuré GPT-4o
            $table->boolean('ai_processed')->default(false);
            // Déduplication
            $table->string('dedup_hash', 64)->unique(); // hash(objet+institution+deadline)
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->index(['country', 'type']);
            $table->index('deadline');
            $table->index('ai_processed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenders');
    }
};
