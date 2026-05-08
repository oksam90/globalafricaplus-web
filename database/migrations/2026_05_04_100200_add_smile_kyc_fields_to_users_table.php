<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 1 — extend users for the Smile Identity flow (spec § 5.3).
 *
 * The `kyc_level` column was created earlier (IDnorm era) as ENUM(none, basic,
 * verified, certified). We keep the column intact and only ADD the new
 * tracking fields here:
 *   - kyc_verified_at / kyc_expires_at
 *   - kyc_verification_id  (FK to the latest active row in kyc_verifications)
 *   - aml_status / aml_last_checked_at
 *   - selfie_registered    (true once Smile holds a selfie for re-auth)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('kyc_verified_at')->nullable()->after('kyc_level');
            $table->timestamp('kyc_expires_at')->nullable()->after('kyc_verified_at');

            // Pointer to the latest approved verification — nullOnDelete to
            // keep audit trail safe if the verification row is hard-deleted.
            $table->foreignId('kyc_verification_id')->nullable()
                  ->after('kyc_expires_at')
                  ->constrained('kyc_verifications')
                  ->nullOnDelete();

            $table->enum('aml_status', ['clear', 'flagged', 'blocked'])
                  ->default('clear')
                  ->after('kyc_verification_id');

            $table->timestamp('aml_last_checked_at')->nullable()->after('aml_status');

            $table->boolean('selfie_registered')->default(false)->after('aml_last_checked_at');

            $table->index(['kyc_level', 'kyc_expires_at']);
            $table->index('aml_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['kyc_level', 'kyc_expires_at']);
            $table->dropIndex(['aml_status']);

            $table->dropForeign(['kyc_verification_id']);
            $table->dropColumn([
                'kyc_verified_at',
                'kyc_expires_at',
                'kyc_verification_id',
                'aml_status',
                'aml_last_checked_at',
                'selfie_registered',
            ]);
        });
    }
};
