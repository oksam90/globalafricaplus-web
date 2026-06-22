<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stocke les informations & documents attendus selon le stade du projet
 * (Idée / MVP / Lancement / Croissance). Le contenu est un objet JSON clé→valeur
 * dont la structure est pilotée côté front (STAGE_CONFIG dans CreateEdit.vue) :
 *   - "informations à fournir" → texte libre
 *   - "documents requis"       → liens (URL) vers les pièces hébergées
 *
 * Le pitch deck reste sur la colonne dédiée `pitch_deck_url` existante et n'est
 * donc pas dupliqué ici.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('stage_details')->nullable()->after('pitch_deck_url');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('stage_details');
        });
    }
};
