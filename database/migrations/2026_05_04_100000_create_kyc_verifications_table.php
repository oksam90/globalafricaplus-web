<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 1 — Smile Identity foundation.
 *
 * Per spec § 5.1, every KYC submission to Smile ID — Basic, Biometric,
 * Document, AML — gets one row here. The `actions` JSON keeps the per-check
 * detail (Selfie_Check, Liveness_Check, etc.) for audit; `callback_payload`
 * stores the raw IPN body for forensic replay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_verifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('smile_job_id', 100)->nullable()->index();   // Smile-side reference
            $table->string('partner_job_id', 64)->unique();             // our own UUID (sent in partner_params.job_id)

            $table->enum('job_type', [
                'basic_kyc',
                'biometric_kyc',
                'document_verification',
                'aml_check',
            ])->index();

            $table->char('country', 2);
            $table->string('id_type', 50);
            // Identity number is a regulated PII — store SHA-256 only, never plaintext.
            $table->string('id_number_hash', 255)->index();

            $table->string('result_code', 10)->nullable()->index();    // 0810 / 0811 / 0812 / …
            $table->string('result_text', 100)->nullable();
            $table->decimal('confidence_value', 5, 2)->nullable();      // 0.00 → 100.00

            $table->json('actions')->nullable();                        // per-check detail

            $table->enum('kyc_level_granted', ['basic', 'verified', 'certified'])->nullable();

            $table->enum('status', ['pending', 'processing', 'approved', 'rejected', 'expired'])
                  ->default('pending')->index();

            $table->json('callback_payload')->nullable();               // raw webhook body

            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'job_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_verifications');
    }
};
