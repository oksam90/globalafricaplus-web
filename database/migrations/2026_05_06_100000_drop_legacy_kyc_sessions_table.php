<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-05 — drop the legacy IDnorm `kyc_sessions` table now that the
 * Smile Identity flow (`kyc_verifications`) is the single source of truth.
 *
 * Self-protecting: if the table still contains rows, this migration first
 * runs `kyc:migrate-sessions --apply` to import them into kyc_verifications
 * before dropping. That keeps the deploy pipeline safe even if an operator
 * forgot the manual pre-step.
 *
 * The down() recreates the bare-minimum structure for rollback safety.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kyc_sessions')) {
            $rowCount = DB::table('kyc_sessions')->count();
            if ($rowCount > 0) {
                Log::warning('drop_legacy_kyc_sessions_table: importing rows before drop', [
                    'rows' => $rowCount,
                ]);
                // The command itself is idempotent (`partner_job_id = "legacy:{id}"`).
                Artisan::call('kyc:migrate-sessions', ['--apply' => true]);
                Log::info('drop_legacy_kyc_sessions_table: import complete', [
                    'output' => Artisan::output(),
                ]);
            }
        }

        Schema::dropIfExists('kyc_sessions');
    }

    public function down(): void
    {
        // Minimal recreate — historical detail is NOT recoverable from this migration.
        Schema::create('kyc_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('idnorm');
            $table->string('provider_session_id')->nullable();
            $table->string('status')->default('pending');
            $table->json('provider_data')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }
};
