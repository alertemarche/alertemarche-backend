<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('phone')->unique()->nullable();          // numéro WhatsApp
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password')->nullable();
            // Profil : prestataire | artisan | admin_public | ong
            $table->string('profile_type')->default('prestataire');
            $table->boolean('is_admin')->default(false);
            // Pays principal (BJ/TG/CI) détecté par géoloc
            $table->string('primary_country', 2)->default('BJ');
            // Secteurs suivis (prestataires/admin/ong) — JSON de codes
            $table->json('sectors')->nullable();
            // Artisan : métier + localité + rayon
            $table->string('artisan_trade')->nullable();
            $table->string('artisan_locality')->nullable();
            $table->unsignedSmallInteger('artisan_radius_km')->nullable();
            // Freemium
            $table->unsignedInteger('free_alerts_used')->default(0);
            $table->boolean('is_suspended')->default(false);
            // Préférences notifications
            $table->boolean('notify_whatsapp')->default(false);
            $table->boolean('notify_email')->default(true);
            $table->rememberToken();
            $table->timestamps();

            $table->index('profile_type');
            $table->index('primary_country');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
