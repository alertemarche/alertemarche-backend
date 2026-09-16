<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('artisan_needs', function (Blueprint $table) {
            $table->boolean('is_premium')->default(false)->after('status');
            $table->timestamp('paid_at')->nullable()->after('is_premium');
            $table->timestamp('expires_at')->nullable()->after('paid_at');
            
            $table->index(['is_premium', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artisan_needs', function (Blueprint $table) {
            $table->dropIndex(['is_premium', 'status']);
            $table->dropColumn(['is_premium', 'paid_at', 'expires_at']);
        });
    }
};
