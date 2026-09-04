<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compte de réception (séquestre) — deux canaux.
 *
 *  1. Mobile Money (canal PRINCIPAL) — décaissement PawaPay lorsque
 *     l'investisseur paie par mobile money.
 *  2. Virement bancaire IBAN (canal SECONDAIRE, déjà en place) — utilisé pour
 *     les investissements réglés par carte bancaire via PayDunya.
 *
 * Les colonnes bancaires ajoutées en juin 2026 sont conservées telles quelles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'payout_mobile_number')) {
                // MSISDN international, chiffres uniquement (ex. 24107123456).
                $table->string('payout_mobile_number', 20)->nullable()->after('currency');
            }
            if (!Schema::hasColumn('projects', 'payout_mobile_provider')) {
                // Code opérateur PawaPay (ex. AIRTEL_GAB, ORANGE_SEN).
                $table->string('payout_mobile_provider', 40)->nullable()->after('payout_mobile_number');
            }
            if (!Schema::hasColumn('projects', 'payout_mobile_country')) {
                $table->char('payout_mobile_country', 2)->nullable()->after('payout_mobile_provider');
            }
            if (!Schema::hasColumn('projects', 'payout_mobile_holder')) {
                $table->string('payout_mobile_holder', 200)->nullable()->after('payout_mobile_country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            foreach ([
                'payout_mobile_number',
                'payout_mobile_provider',
                'payout_mobile_country',
                'payout_mobile_holder',
            ] as $col) {
                if (Schema::hasColumn('projects', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
