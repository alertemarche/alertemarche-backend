<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Source de l'alerte : tender (appel d'offres) ou artisan_need (besoin)
            $table->string('source_type');               // tender | artisan_need
            $table->unsignedBigInteger('source_id');
            $table->string('title');
            $table->text('message')->nullable();         // contenu envoyé
            $table->decimal('relevance_score', 5, 2)->nullable(); // score IA 0-100
            // Canaux
            $table->boolean('sent_email')->default(false);
            $table->boolean('sent_whatsapp')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->boolean('is_free')->default(false);  // comptée dans le quota gratuit
            $table->string('status')->default('queued'); // queued|sent|failed
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
