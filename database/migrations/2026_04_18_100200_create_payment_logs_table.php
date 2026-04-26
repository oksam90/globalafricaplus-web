<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 1 — `payment_logs` table.
 *
 * Audit trail for every incoming webhook and every outgoing payment request.
 * Immutable records used for reconciliation, fraud analysis and dispute
 * resolution (retention: 5 years per DPA §10).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();

            // Optional link to a transaction (IPN may arrive before the
            // transaction row is created — so this is nullable).
            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete();

            $table->enum('gateway', ['paydunya', 'flutterwave', 'stripe', 'paypal', 'manual'])
                ->default('paydunya');

            // Event classification
            $table->string('event_type', 80); // ipn.received, checkout.created, verify.success, refund.requested, etc.
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');

            // Raw payload (request or response body)
            $table->json('payload')->nullable();

            // Network / auth metadata
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('signature', 255)->nullable(); // HMAC header received
            $table->boolean('signature_valid')->nullable();

            // HTTP transport metadata
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('http_method', 10)->nullable();
            $table->string('endpoint', 255)->nullable();

            // Correlation
            $table->string('gateway_reference', 200)->nullable()->index();
            $table->string('correlation_id', 64)->nullable()->index();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['gateway', 'event_type']);
            $table->index(['transaction_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
