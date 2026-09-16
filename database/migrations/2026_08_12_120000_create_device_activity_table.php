<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `device_activity` : suivi interne des appareils connectés.
 * Chaque ligne = un appareil (identifié par une empreinte = hash de
 * user_id + user-agent) avec sa dernière activité. Alimentée par le
 * middleware TrackDeviceActivity sur les requêtes API authentifiées,
 * et exploitée par le back-office (bloc « Appareils connectés »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fingerprint', 64)->unique();
            $table->string('device_type', 16)->default('desktop'); // desktop | mobile | tablet
            $table->string('browser', 64)->nullable();
            $table->string('platform', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->index(['device_type', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_activity');
    }
};
