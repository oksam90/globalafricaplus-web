<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 1 — `transactions` table with native PayDunya fields.
 *
 * The spec (§5) describes "modifications to the transactions table" but no
 * such table existed in the codebase yet — so we create it with the full
 * shape required by the integration.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Ownership
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('transactable'); // subscription, investment, training, donation...

            // Core amounts
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('XOF');
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->decimal('gateway_fee', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);

            // Gateway
            $table->enum('gateway', ['paydunya', 'flutterwave', 'stripe', 'paypal', 'manual'])
                ->default('paydunya');
            $table->string('gateway_reference', 200)->nullable()->index();

            // PayDunya-specific
            $table->string('paydunya_token', 100)->nullable()->unique();
            $table->text('paydunya_invoice_url')->nullable();
            $table->string('paydunya_receipt_url')->nullable();
            $table->string('paydunya_channel', 50)->nullable(); // orange-money-senegal, wave-senegal, ...

            // Business classification
            $table->enum('payment_type', [
                'subscription',
                'investment',
                'donation',
                'training',
                'refund',
                'disbursement',
                'escrow_release',
            ])->default('subscription');

            // Fractional / installment payments (Sprint 5)
            $table->unsignedTinyInteger('installment_number')->nullable();
            $table->unsignedTinyInteger('installment_total')->nullable();

            // Status
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'cancelled',
                'refunded',
                'disputed',
            ])->default('pending');

            // Customer snapshot (immutable for audit)
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->char('customer_country', 2)->nullable();

            // Metadata
            $table->text('description')->nullable();
            $table->json('custom_data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Lifecycle
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['gateway', 'status']);
            $table->index('payment_type');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
