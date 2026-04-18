<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Country investment guides (Guide Pays)
        Schema::create('country_guides', function (Blueprint $table) {
            $table->id();
            $table->string('country')->unique();
            $table->string('country_code', 2)->unique();
            $table->string('flag', 10)->nullable();
            $table->string('currency', 3)->default('XOF');
            $table->string('official_language')->default('Français');
            $table->unsignedInteger('population')->default(0);            // millions
            $table->decimal('gdp', 14, 2)->default(0);                    // milliards USD
            $table->decimal('gdp_growth', 5, 2)->default(0);              // % annuel
            $table->decimal('remittances_gdp', 5, 2)->default(0);         // % du PIB
            $table->decimal('ease_of_business_score', 5, 1)->default(0);  // 0-100
            $table->json('key_sectors')->nullable();                       // ["Agritech","Fintech","Énergie"]
            $table->text('legal_framework')->nullable();                   // HTML/Markdown
            $table->text('taxation')->nullable();                          // HTML/Markdown
            $table->text('investment_incentives')->nullable();              // HTML/Markdown
            $table->text('risks')->nullable();                             // HTML/Markdown
            $table->text('opportunities')->nullable();                     // HTML/Markdown
            $table->text('diaspora_programs')->nullable();                  // gov programs for diaspora
            $table->string('investment_agency')->nullable();                // ex: APIX (Sénégal)
            $table->string('investment_agency_url')->nullable();
            $table->timestamps();
        });

        // Diaspora transfer simulation logs (for analytics, optional tracking)
        Schema::create('diaspora_simulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('origin_country');
            $table->string('destination_country');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('investment_type')->default('equity'); // equity, donation, loan, reward
            $table->unsignedSmallInteger('duration_months')->default(24);
            $table->decimal('estimated_return', 14, 2)->default(0);
            $table->decimal('estimated_jobs', 8, 1)->default(0);
            $table->string('target_sector')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diaspora_simulations');
        Schema::dropIfExists('country_guides');
    }
};
