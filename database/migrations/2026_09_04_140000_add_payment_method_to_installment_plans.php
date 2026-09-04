<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le plan d'échéances doit mémoriser le moyen de paiement choisi par
 * l'utilisateur (mobile_money / card).
 *
 * Sans cette colonne, `InstallmentService::invoiceNext()` retombait sur le PSP
 * par défaut du pays : un investisseur ayant choisi « Carte bancaire » était
 * redirigé vers PawaPay au lieu de PayDunya dès la 1re échéance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installment_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('installment_plans', 'payment_method')) {
                $table->string('payment_method', 20)->nullable()->after('payment_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('installment_plans', function (Blueprint $table) {
            if (Schema::hasColumn('installment_plans', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
