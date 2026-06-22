<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remplace les champs Mobile Money du compte de réception (séquestre) par les
 * coordonnées d'un virement bancaire au nom de l'entreprise porteuse du projet.
 *
 *   payout_phone     → payout_account_holder (raison sociale)
 *   payout_provider  → payout_bank_name      (nom de la banque)
 *   payout_country   → payout_bank_country   (pays ISO 2)
 *   (new)            → payout_iban           (IBAN max 34)
 *   (new)            → payout_bic            (BIC/SWIFT 8 ou 11)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['payout_phone', 'payout_provider', 'payout_country']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('payout_account_holder', 200)->nullable()->after('currency');
            $table->string('payout_bank_name', 150)->nullable()->after('payout_account_holder');
            $table->string('payout_iban', 34)->nullable()->after('payout_bank_name');
            $table->string('payout_bic', 11)->nullable()->after('payout_iban');
            $table->char('payout_bank_country', 2)->nullable()->after('payout_bic');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'payout_account_holder',
                'payout_bank_name',
                'payout_iban',
                'payout_bic',
                'payout_bank_country',
            ]);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('payout_phone', 30)->nullable()->after('currency');
            $table->string('payout_provider', 50)->nullable()->after('payout_phone');
            $table->char('payout_country', 2)->nullable()->after('payout_provider');
        });
    }
};
