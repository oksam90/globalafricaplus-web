<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 5 — Achats de formations (lien user ↔ training avec accès et garantie 30j).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->enum('status', ['pending', 'active', 'refunded', 'cancelled', 'failed'])
                ->default('pending')
                ->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refund_deadline')->nullable(); // paid_at + 30 jours
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('access_revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'training_id']);
            $table->index(['status', 'refund_deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_purchases');
    }
};
