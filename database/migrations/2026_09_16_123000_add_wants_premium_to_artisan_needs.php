<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artisan_needs', function (Blueprint $table) {
            $table->boolean('wants_premium')->default(false)->after('is_premium');
            $table->string('payment_token', 64)->nullable()->after('wants_premium');
        });
    }

    public function down(): void
    {
        Schema::table('artisan_needs', function (Blueprint $table) {
            $table->dropColumn(['wants_premium', 'payment_token']);
        });
    }
};
