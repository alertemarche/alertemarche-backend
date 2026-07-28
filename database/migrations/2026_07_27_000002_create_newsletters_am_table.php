<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table newsletters_am (suffixe _am = AlerteMarché) — campagnes d'e-mails
 * (newsletters et annonces publicitaires) envoyées depuis le back-office,
 * avec ciblage : tous / par secteur / par pays.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newsletters_am')) {
            return;
        }

        Schema::create('newsletters_am', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('body');
            $table->string('type')->default('newsletter');        // newsletter | pub
            $table->string('target_type')->default('all');         // all | by_sector | by_country
            $table->foreignId('target_sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->string('target_country', 5)->nullable();       // BJ | TG | CI | SN | ALL
            $table->unsignedInteger('sent_count')->default(0);
            $table->string('status')->default('draft');            // draft | sending | sent
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletters_am');
    }
};
