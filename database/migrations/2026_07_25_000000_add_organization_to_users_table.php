<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persiste le nom de la structure / entreprise du souscripteur.
 * Ce champ était collecté au formulaire d'inscription mais n'était pas
 * enregistré ; il est désormais stocké et modifiable depuis l'espace
 * abonné, et affiché dans le back-office (colonne « Entreprise »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('organization')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('organization');
        });
    }
};
