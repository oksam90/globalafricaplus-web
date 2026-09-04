<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `formalization_steps.order` était un TINYINT UNSIGNED (0-255).
 *
 * Le réordonnancement d'un parcours se fait en deux passes (positions
 * temporaires hors plage, puis positions finales) pour ne pas violer l'index
 * unique (country, order) en cours de route. Avec un TINYINT UNSIGNED, aucune
 * valeur tampon n'était disponible : l'écriture échouait en
 * « Out of range value for column 'order' ».
 *
 * On passe en SMALLINT UNSIGNED (0-65535) : la plage 1000+ sert de zone
 * tampon, l'ordre réel restant sur de petits entiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formalization_steps', function (Blueprint $table) {
            $table->unsignedSmallInteger('order')->change();
        });
    }

    public function down(): void
    {
        Schema::table('formalization_steps', function (Blueprint $table) {
            $table->unsignedTinyInteger('order')->change();
        });
    }
};
