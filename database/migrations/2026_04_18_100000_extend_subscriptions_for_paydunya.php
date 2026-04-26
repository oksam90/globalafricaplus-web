<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 1 — extend the existing `subscriptions` table with the fields
 * required by the PayDunya integration spec (§5 Modèle de données).
 *
 * Idempotent: guards every column addition with Schema::hasColumn().
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'plan_slug')) {
                // Denormalised plan slug (duplicates plans.slug for fast filtering)
                // NB: named `plan_slug` (not `plan`) to avoid clashing with
                //     the Eloquent BelongsTo relation `plan()` on Subscription.
                $table->enum('plan_slug', ['starter', 'pro', 'enterprise'])
                    ->default('starter')
                    ->after('plan_id');
            }

            if (!Schema::hasColumn('subscriptions', 'amount')) {
                $table->decimal('amount', 10, 2)->default(0)->after('billing_cycle');
            }

            if (!Schema::hasColumn('subscriptions', 'currency')) {
                $table->char('currency', 3)->default('XOF')->after('amount');
            }

            if (!Schema::hasColumn('subscriptions', 'payment_gateway')) {
                $table->enum('payment_gateway', ['paydunya', 'flutterwave', 'stripe', 'paypal'])
                    ->nullable()
                    ->after('currency');
            }

            if (!Schema::hasColumn('subscriptions', 'gateway_subscription_ref')) {
                $table->string('gateway_subscription_ref', 200)->nullable()->after('payment_gateway');
            }

            if (!Schema::hasColumn('subscriptions', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
            }

            if (!Schema::hasColumn('subscriptions', 'gateway_metadata')) {
                $table->json('gateway_metadata')->nullable()->after('payment_reference');
            }
        });

        // Extend the status ENUM to include 'refunded'
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status
                ENUM('active','trialing','past_due','cancelled','expired','refunded')
                NOT NULL DEFAULT 'trialing'");
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            foreach ([
                'plan_slug',
                'amount',
                'currency',
                'payment_gateway',
                'gateway_subscription_ref',
                'refunded_at',
                'gateway_metadata',
            ] as $col) {
                if (Schema::hasColumn('subscriptions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status
                ENUM('active','trialing','past_due','cancelled','expired')
                NOT NULL DEFAULT 'trialing'");
        }
    }
};
