<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute le type de procédure de passation (procedure_type) aux appels d'offres.
 *
 * Valeurs canoniques (sous-catégories « Appels d'offres publics ») :
 *   - cotation : Demande de cotation (DC)
 *   - drp      : Demande de renseignement et de prix (DRP)
 *   - aaon     : Avis d'appel d'offres national (AOO, AOR…)
 *   - aaoi     : Avis d'appel d'offres international (AOI, AOOIP, AOIR…)
 *   - ami      : Avis à manifestation d'intérêt (AMI, AMII)
 *   - autre    : autres modes (gré à gré/entente directe, consultation restreinte…)
 *
 * La valeur reste NULL pour les enregistrements non classables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->string('procedure_type', 20)->nullable()->after('market_type');
            $table->index('procedure_type');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropIndex(['procedure_type']);
            $table->dropColumn('procedure_type');
        });
    }
};
