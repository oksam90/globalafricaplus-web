<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05 — payment_logs.transaction_id explicit single-column index.
 *
 * Why a dedicated index when a composite (transaction_id, created_at) already exists:
 *
 *   - MySQL InnoDB auto-creates an index for FK columns when no usable one
 *     exists; the existing composite covers leftmost-prefix lookups, but only
 *     for the SGBD that resolves it. Defensive single-column index makes the
 *     plan optimal across MySQL / Postgres / SQLite without optimiser quirks.
 *
 *   - The composite stays — it serves the "last N events for this transaction"
 *     pattern (ORDER BY created_at DESC), which is the audit-trail use case.
 *
 *   - This table grows linearly with retention (5 y for LCB-FT). At 100k+ rows
 *     a missed FK plan costs full-table scans on routine joins.
 *
 * Reference: Sprint audit § 4.1.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard against MySQL auto-created indexes (named `payment_logs_transaction_id_foreign`).
        // `payment_logs_transaction_id_index` is the explicit name we want regardless.
        Schema::table('payment_logs', function (Blueprint $table) {
            $table->index('transaction_id', 'payment_logs_transaction_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('payment_logs', function (Blueprint $table) {
            $table->dropIndex('payment_logs_transaction_id_index');
        });
    }
};
