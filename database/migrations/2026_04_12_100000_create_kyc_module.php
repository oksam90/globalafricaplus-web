<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('idnorm');               // idnorm, manual, etc.
            $table->string('provider_session_id')->nullable();           // IDNorm session ID
            $table->string('redirect_url')->nullable();                  // IDNorm verification URL
            $table->enum('status', [
                'pending',          // session created, waiting for user
                'in_progress',      // user started verification
                'documents_submitted', // user submitted docs, awaiting review
                'verified',         // KYC approved
                'rejected',         // KYC rejected
                'expired',          // session expired
            ])->default('pending');
            $table->unsignedTinyInteger('current_step')->default(0);     // 0-4 (which step user is on)

            // Step 1: Identity data
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('gender')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->enum('person_type', ['physical', 'moral'])->default('physical');

            // Step 1b: Legal entity (person_type = moral)
            $table->string('company_name')->nullable();
            $table->string('company_address')->nullable();
            $table->string('company_registration_number')->nullable();   // RCCM
            $table->string('legal_form')->nullable();                    // SARL, SA, SAS, etc.
            $table->string('legal_representative_name')->nullable();

            // Step 2: Document verification
            $table->enum('document_type', ['cni', 'passport', 'permis', 'carte_sejour'])->nullable();
            $table->string('document_number')->nullable();
            $table->date('document_expiry')->nullable();
            $table->string('document_issuing_country')->nullable();
            $table->string('document_front_url')->nullable();
            $table->string('document_back_url')->nullable();
            $table->string('selfie_url')->nullable();
            $table->string('proof_of_address_url')->nullable();
            // For moral persons
            $table->string('rccm_url')->nullable();
            $table->string('statuts_url')->nullable();

            // Step 3: Risk assessment
            $table->enum('risk_level', ['low', 'medium', 'high'])->nullable();
            $table->string('source_of_funds')->nullable();
            $table->string('occupation')->nullable();
            $table->decimal('expected_monthly_volume', 12, 2)->nullable();
            $table->boolean('is_pep')->default(false);                   // Politically Exposed Person
            $table->json('risk_factors')->nullable();                    // automated risk factors

            // Step 4: AML check
            $table->boolean('aml_checked')->default(false);
            $table->boolean('aml_clear')->nullable();                    // true=clean, false=flagged
            $table->json('aml_results')->nullable();                     // screening results
            $table->boolean('sanctions_clear')->nullable();
            $table->boolean('pep_clear')->nullable();

            // Verification results from IDNorm
            $table->json('provider_data')->nullable();                   // raw IDNorm callback data
            $table->string('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_sessions');
    }
};
