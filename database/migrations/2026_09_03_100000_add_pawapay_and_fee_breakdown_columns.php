<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intégration PawaPay + ventilation des frais.
 *
 * - transactions : identifiant de dépôt PawaPay + décomposition des frais.
 * - investments  : « Montant Reçu » (net porteur) vs « Montant Envoyé »
 *                  (débit investisseur) + commission et frais PSP.
 *
 * Les colonnes PayDunya (paydunya_token, paydunya_invoice_url…) sont
 * CONSERVÉES : le PSP reste réactivable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'pawapay_deposit_id')) {
                $table->string('pawapay_deposit_id', 36)->nullable()->after('paydunya_channel')->index();
            }
            if (!Schema::hasColumn('transactions', 'pawapay_provider')) {
                $table->string('pawapay_provider', 40)->nullable()->after('pawapay_deposit_id');
            }
            if (!Schema::hasColumn('transactions', 'pawapay_checkout_url')) {
                $table->string('pawapay_checkout_url', 500)->nullable()->after('pawapay_provider');
            }
            if (!Schema::hasColumn('transactions', 'fee_breakdown')) {
                $table->json('fee_breakdown')->nullable()->after('net_amount');
            }
        });

        Schema::table('investments', function (Blueprint $table) {
            // Montant Reçu — ce que le porteur touche réellement, dans la
            // devise mobile money du marché du projet.
            if (!Schema::hasColumn('investments', 'net_amount')) {
                $table->decimal('net_amount', 15, 2)->nullable()->after('charged_currency');
            }
            // Commission GlobalAfrica+ (barème 3 % / 2 % / 1 %).
            if (!Schema::hasColumn('investments', 'platform_fee')) {
                $table->decimal('platform_fee', 15, 2)->nullable()->after('net_amount');
            }
            if (!Schema::hasColumn('investments', 'platform_fee_rate')) {
                $table->decimal('platform_fee_rate', 6, 4)->nullable()->after('platform_fee');
            }
            // Frais PSP (collecte + décaissement) supportés par l'investisseur.
            if (!Schema::hasColumn('investments', 'provider_fee')) {
                $table->decimal('provider_fee', 15, 2)->nullable()->after('platform_fee_rate');
            }
            if (!Schema::hasColumn('investments', 'fee_currency')) {
                $table->string('fee_currency', 3)->nullable()->after('provider_fee');
            }
            if (!Schema::hasColumn('investments', 'fee_breakdown')) {
                $table->json('fee_breakdown')->nullable()->after('fee_currency');
            }
            if (!Schema::hasColumn('investments', 'pawapay_deposit_id')) {
                $table->string('pawapay_deposit_id', 36)->nullable()->after('paydunya_channel')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            foreach (['pawapay_deposit_id', 'pawapay_provider', 'pawapay_checkout_url', 'fee_breakdown'] as $col) {
                if (Schema::hasColumn('transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('investments', function (Blueprint $table) {
            foreach ([
                'net_amount', 'platform_fee', 'platform_fee_rate',
                'provider_fee', 'fee_currency', 'fee_breakdown', 'pawapay_deposit_id',
            ] as $col) {
                if (Schema::hasColumn('investments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
