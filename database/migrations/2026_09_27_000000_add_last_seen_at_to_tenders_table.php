<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('collected_at');
        });

        // Backfill : pour tous les marchés existants, last_seen_at = collected_at
        // (meilleure approximation disponible)
        \DB::statement('UPDATE tenders SET last_seen_at = collected_at WHERE last_seen_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
