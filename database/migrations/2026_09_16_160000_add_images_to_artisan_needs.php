<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artisan_needs', function (Blueprint $table) {
            $table->string('image1', 512)->nullable()->after('payment_token');
            $table->string('image2', 512)->nullable()->after('image1');
        });
    }

    public function down(): void
    {
        Schema::table('artisan_needs', function (Blueprint $table) {
            $table->dropColumn(['image1', 'image2']);
        });
    }
};
