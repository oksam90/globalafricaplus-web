<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'URL de la Payment Page PawaPay embarque un jeton chiffré et dépasse
 * largement 500 caractères (≈ 2 700 observés en sandbox), ce qui provoquait
 * un SQLSTATE[22001] au moment d'enregistrer le checkout.
 *
 * `paydunya_invoice_url` est déjà un TEXT — on aligne la colonne PawaPay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'pawapay_checkout_url')) {
                $table->text('pawapay_checkout_url')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'pawapay_checkout_url')) {
                $table->string('pawapay_checkout_url', 500)->nullable()->change();
            }
        });
    }
};
