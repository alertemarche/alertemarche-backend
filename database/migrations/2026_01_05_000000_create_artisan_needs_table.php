<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artisan_needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publisher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trade');                     // domaine : maçonnerie...
            $table->string('employer_name')->nullable(); // entreprise / "Entrepreneur privé"
            $table->string('people_needed')->nullable(); // ex : "10 maçons qualifiés"
            $table->text('description')->nullable();
            $table->string('locality');                  // Parakou
            $table->string('region')->nullable();        // Borgou
            $table->string('country', 2);                // BJ/TG/CI
            $table->string('estimated_budget')->nullable();
            $table->string('duration')->nullable();
            $table->date('start_date')->nullable();
            $table->string('contact');                   // lien ou numéro
            // Validation éditoriale
            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            // IA
            $table->text('ai_summary')->nullable();
            $table->boolean('ai_processed')->default(false);
            $table->timestamps();

            $table->index(['country', 'status']);
            $table->index('trade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artisan_needs');
    }
};
