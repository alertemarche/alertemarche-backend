<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute une colonne `title_fr` pour stocker la traduction française du titre.
 * Les avis privés (UNGM : ONU, ambassades, ONG) arrivent souvent en anglais ;
 * le pipeline IA remplit ce champ afin d'afficher un titre en français.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->string('title_fr', 500)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn('title_fr');
        });
    }
};
