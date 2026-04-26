<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 — Capture the project owner's mobile-money payout details so the
 * platform can disburse escrow funds via PayDunya DirectPay when an investor
 * validates a milestone.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('payout_phone', 30)->nullable()->after('currency');
            $table->string('payout_provider', 50)->nullable()->after('payout_phone');
            $table->char('payout_country', 2)->nullable()->after('payout_provider');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['payout_phone', 'payout_provider', 'payout_country']);
        });
    }
};
