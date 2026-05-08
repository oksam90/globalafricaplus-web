<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 5 — relax `payment_logs.gateway` from a strict ENUM to a varchar(40)
 * so we can record audit entries for non-payment providers (smile_identity,
 * centif, idnorm-legacy, …) without altering the enum every time a new
 * compliance source comes online.
 *
 * This table is append-only (audit) so the change is non-destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Doctrine's enum -> string conversion needs the type to be remapped first,
        // but Laravel 11+ uses native schema operations. The safest portable change
        // is a rename-and-recreate via the Schema::table closure.
        Schema::table('payment_logs', function (Blueprint $table) {
            $table->string('gateway', 40)->default('manual')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_logs', function (Blueprint $table) {
            // Restoring the original ENUM is best-effort; rows with newer values
            // (smile_identity, centif…) would fail to round-trip. We keep the
            // string column on rollback rather than risk dropping audit data.
            $table->string('gateway', 40)->default('manual')->change();
        });
    }
};
