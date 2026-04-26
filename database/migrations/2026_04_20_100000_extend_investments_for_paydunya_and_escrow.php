<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3 — extend `investments` with PayDunya gateway fields + escrow ties,
 * and create `escrow_milestones` for milestone-based fund release.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            // Link back to the gateway transaction (nullable for legacy / manual investments).
            $table->foreignId('transaction_id')
                ->nullable()
                ->after('provider_reference')
                ->constrained('transactions')
                ->nullOnDelete();

            // PayDunya-native fields (mirrors transactions table so we don't need a join for audits).
            $table->string('paydunya_token', 100)->nullable()->after('transaction_id');
            $table->string('paydunya_receipt_url')->nullable()->after('paydunya_token');
            $table->string('paydunya_channel', 50)->nullable()->after('paydunya_receipt_url');

            // Snapshot of the amount actually charged on the gateway (after EUR→XOF conversion).
            $table->decimal('charged_amount', 14, 2)->nullable()->after('amount');
            $table->char('charged_currency', 3)->nullable()->after('charged_amount');

            $table->timestamp('paid_at')->nullable()->after('status');
            $table->timestamp('refunded_at')->nullable()->after('paid_at');

            $table->index(['project_id', 'status']);
            $table->index(['investor_id', 'status']);
        });

        Schema::create('escrow_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_id')
                ->nullable()
                ->constrained('investments')
                ->nullOnDelete();

            $table->unsignedTinyInteger('position')->default(1);
            $table->string('title');
            $table->text('description')->nullable();

            // Portion of the investment that is released on this milestone.
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('XOF');
            $table->unsignedTinyInteger('percentage')->nullable(); // convenience: % of total

            $table->enum('status', [
                'pending',      // not yet due
                'in_review',    // entrepreneur submitted proof
                'approved',     // admin/validator approved release
                'released',     // funds actually disbursed (linked to a disbursement Transaction)
                'rejected',
                'cancelled',
            ])->default('pending');

            $table->date('due_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('released_at')->nullable();

            // Disbursement transaction, when released.
            $table->foreignId('release_transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete();

            $table->json('evidence')->nullable(); // urls / notes submitted by entrepreneur
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['investment_id', 'status']);
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrow_milestones');

        Schema::table('investments', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status']);
            $table->dropIndex(['investor_id', 'status']);
            $table->dropForeign(['transaction_id']);
            $table->dropColumn([
                'transaction_id',
                'paydunya_token',
                'paydunya_receipt_url',
                'paydunya_channel',
                'charged_amount',
                'charged_currency',
                'paid_at',
                'refunded_at',
            ]);
        });
    }
};
