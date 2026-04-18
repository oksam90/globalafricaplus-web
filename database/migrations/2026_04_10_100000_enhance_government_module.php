<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enrich government_calls
        Schema::table('government_calls', function (Blueprint $table) {
            $table->text('eligibility_criteria')->nullable()->after('sector');
            $table->text('required_documents')->nullable()->after('eligibility_criteria');
            $table->text('evaluation_criteria')->nullable()->after('required_documents');
            $table->string('geographic_zone')->nullable()->after('country');
            $table->unsignedInteger('views_count')->default(0)->after('status');
            $table->unsignedInteger('applications_count')->default(0)->after('views_count');
            $table->timestamp('published_at')->nullable()->after('applications_count');
        });

        // Applications (candidatures) to government calls
        Schema::create('call_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained('government_calls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->text('motivation');
            $table->text('proposal')->nullable();
            $table->enum('status', ['submitted', 'under_review', 'shortlisted', 'accepted', 'rejected'])->default('submitted');
            $table->text('review_notes')->nullable(); // internal notes by government
            $table->unsignedTinyInteger('score')->nullable(); // 0-100
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['call_id', 'user_id']); // one application per user per call
        });

        // Economic zones (ZES)
        Schema::create('economic_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // government user
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('country');
            $table->string('region')->nullable();
            $table->text('description')->nullable();
            $table->json('incentives')->nullable(); // tax breaks, etc.
            $table->json('sectors')->nullable(); // targeted sectors
            $table->decimal('area_hectares', 10, 2)->nullable();
            $table->string('status')->default('active'); // active, planned, closed
            $table->string('website')->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_zones');
        Schema::dropIfExists('call_applications');

        Schema::table('government_calls', function (Blueprint $table) {
            $table->dropColumn([
                'eligibility_criteria', 'required_documents', 'evaluation_criteria',
                'geographic_zone', 'views_count', 'applications_count', 'published_at',
            ]);
        });
    }
};
