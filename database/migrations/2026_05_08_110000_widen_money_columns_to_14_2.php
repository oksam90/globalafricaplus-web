<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05 — align all money columns on decimal(14, 2).
 *
 * Background:
 *   - `investments.amount`, `projects.amount_needed/raised` already use (14, 2).
 *   - `subscription_plans.price_monthly/yearly`, `subscriptions.amount`,
 *     `trainings.price` and `training_purchases.amount` were on (10, 2) (max
 *     ≈ 100 M XOF) — technically sufficient but inconsistent with the rest of
 *     the schema. Widening here avoids implicit casts on cross-table joins
 *     and simplifies audit/reporting.
 *
 * Reference: Sprint audit § 4.1.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('price_monthly', 14, 2)->default(0)->change();
            $table->decimal('price_yearly',  14, 2)->default(0)->change();
        });

        if (Schema::hasColumn('subscriptions', 'amount')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->decimal('amount', 14, 2)->default(0)->change();
            });
        }

        Schema::table('trainings', function (Blueprint $table) {
            $table->decimal('price', 14, 2)->change();
        });

        Schema::table('training_purchases', function (Blueprint $table) {
            $table->decimal('amount', 14, 2)->change();
        });
    }

    public function down(): void
    {
        // Narrowing back is safe only if no row exceeds 99 999 999.99 — we
        // don't truncate silently, we let MySQL refuse and surface the error.
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('price_monthly', 10, 2)->default(0)->change();
            $table->decimal('price_yearly',  10, 2)->default(0)->change();
        });

        if (Schema::hasColumn('subscriptions', 'amount')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->decimal('amount', 10, 2)->default(0)->change();
            });
        }

        Schema::table('trainings', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->change();
        });

        Schema::table('training_purchases', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });
    }
};
