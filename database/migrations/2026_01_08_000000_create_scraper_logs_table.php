<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scraper_logs', function (Blueprint $table) {
            $table->id();
            $table->string('country', 2);
            $table->string('source_name');
            $table->string('status')->default('success'); // success | failure
            $table->unsignedInteger('items_collected')->default(0);
            $table->unsignedInteger('items_new')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('ran_at')->nullable();
            $table->timestamps();

            $table->index(['country', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraper_logs');
    }
};
