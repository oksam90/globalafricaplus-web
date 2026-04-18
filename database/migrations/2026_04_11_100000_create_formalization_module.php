<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Steps per country — adapted legal pathway (RG-029)
        Schema::create('formalization_steps', function (Blueprint $table) {
            $table->id();
            $table->string('country', 80);
            $table->unsignedTinyInteger('order');               // 1, 2, 3…
            $table->string('title', 200);
            $table->string('slug', 200);
            $table->text('description');
            $table->string('institution', 200)->nullable();      // e.g. APIX, CEPICI, RDB
            $table->json('required_documents')->nullable();      // ["Pièce d'identité","Statuts"]
            $table->string('estimated_duration', 100)->nullable(); // e.g. "3-5 jours ouvrés"
            $table->string('estimated_cost', 100)->nullable();   // e.g. "10 000 XOF" / "Gratuit"
            $table->string('link', 300)->nullable();             // lien officiel
            $table->text('tips')->nullable();                    // conseils pratiques
            $table->timestamps();

            $table->unique(['country', 'order']);
            $table->index('country');
        });

        // User progress on formalization steps
        Schema::create('formalization_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('formalization_steps')->cascadeOnDelete();
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'step_id']);
        });

        // Business plan templates (RG-030: gratuit/freemium)
        Schema::create('business_plan_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->string('sector', 100);                       // agriculture, commerce, artisanat, services, tech
            $table->text('description');
            $table->json('sections');                             // [{"title":"Résumé","prompt":"Décrivez…"},…]
            $table->string('language', 20)->default('fr');
            $table->boolean('is_free')->default(true);            // RG-030
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamps();

            $table->index('sector');
        });

        // Microfinance partners
        Schema::create('microfinance_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->string('country', 80);
            $table->text('description')->nullable();
            $table->string('type', 80);                          // IMF, banque, fintech
            $table->json('products')->nullable();                 // ["Micro-crédit","Épargne"]
            $table->string('min_amount', 50)->nullable();
            $table->string('max_amount', 50)->nullable();
            $table->string('interest_rate', 50)->nullable();
            $table->string('website', 300)->nullable();
            $table->string('contact_email', 200)->nullable();
            $table->string('logo', 300)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microfinance_partners');
        Schema::dropIfExists('business_plan_templates');
        Schema::dropIfExists('formalization_progress');
        Schema::dropIfExists('formalization_steps');
    }
};
