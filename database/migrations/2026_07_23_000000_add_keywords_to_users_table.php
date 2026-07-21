<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute une liste de mots-clés personnalisés à chaque utilisateur.
 * Ces mots-clés affinent le filtrage des notifications : un marché est
 * notifié si son secteur correspond OU si l'un de ces mots-clés apparaît
 * dans l'intitulé du marché.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('keywords')->nullable()->after('sectors');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('keywords');
        });
    }
};
