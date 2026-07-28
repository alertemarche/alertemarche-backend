<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table pivot user_sectors — permet d'associer explicitement des utilisateurs
 * à des secteurs (attribution manuelle / import CSV par l'admin), en complément
 * du champ JSON `users.sectors` (secteurs déclarés à l'inscription).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_sectors')) {
            return;
        }

        Schema::create('user_sectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained('sectors')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sectors');
    }
};
