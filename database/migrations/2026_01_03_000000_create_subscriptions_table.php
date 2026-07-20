<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('profile_type');            // prestataire|artisan|admin_public|ong
            $table->json('countries');                 // ["BJ","TG"]
            $table->unsignedTinyInteger('country_count')->default(1);
            $table->unsignedInteger('base_price');     // tarif de base FCFA/pays
            $table->unsignedInteger('amount');         // total facturé
            $table->boolean('promo_applied')->default(true);
            $table->string('status')->default('pending'); // pending|active|expired|cancelled
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->string('payment_reference')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
