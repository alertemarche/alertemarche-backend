<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichit la table `sectors` (déjà existante : code, name, type) avec les
 * colonnes attendues par le module Newsletter/Pub de l'admin :
 * slug, icon (emoji), description. Colonnes nullables → aucun impact sur
 * les données ni le code existants.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sectors')) {
            return;
        }

        Schema::table('sectors', function (Blueprint $table) {
            if (! Schema::hasColumn('sectors', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
            if (! Schema::hasColumn('sectors', 'icon')) {
                $table->string('icon')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('sectors', 'description')) {
                $table->text('description')->nullable()->after('icon');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sectors')) {
            return;
        }

        Schema::table('sectors', function (Blueprint $table) {
            foreach (['slug', 'icon', 'description'] as $col) {
                if (Schema::hasColumn('sectors', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
