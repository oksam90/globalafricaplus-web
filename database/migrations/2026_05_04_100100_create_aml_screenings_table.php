<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 1 — AML screening results (spec § 5.2).
 *
 * One row per AML check. `auto_reported = true` is set by the listener
 * ReportSuspiciousActivity when a sanctions / PEP hit is detected, indicating
 * a regulatory declaration has been auto-emitted to the Cellule Nationale de
 * Traitement des Informations Financières (CENTIF).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aml_screenings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kyc_verification_id')->nullable()
                  ->constrained('kyc_verifications')->nullOnDelete();

            $table->string('smile_job_id', 100)->nullable()->index();

            $table->string('full_name_screened', 255);
            $table->json('countries');                                  // ["SN","FR"]
            $table->char('birth_year', 4);

            $table->string('result_code', 10)->nullable();              // 1020 = match, 1022 = no match

            $table->boolean('sanctions_match')->default(false);
            $table->boolean('pep_match')->default(false);
            $table->boolean('adverse_media_match')->default(false);

            $table->json('matches')->nullable();

            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low')->index();

            $table->boolean('auto_reported')->default(false);

            $table->timestamp('screened_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'risk_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aml_screenings');
    }
};
