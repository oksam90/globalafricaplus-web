<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 — Composite index on (status, paid_at) so the daily auto-refund
 * scan can efficiently find investments stuck in escrow past the 90-day
 * window without a full table scan.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->index(['status', 'paid_at'], 'investments_status_paid_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropIndex('investments_status_paid_at_index');
        });
    }
};
