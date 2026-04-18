<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('role_applied', 150);           // poste visé
            $table->text('motivation');                      // lettre de motivation
            $table->text('proposal')->nullable();            // proposition technique
            $table->string('cv_url', 300)->nullable();       // CV link
            $table->enum('status', ['submitted', 'under_review', 'shortlisted', 'accepted', 'rejected'])->default('submitted');
            $table->text('review_notes')->nullable();        // notes internes entrepreneur
            $table->unsignedTinyInteger('score')->nullable(); // 0-100
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'project_id']);       // one application per project per user
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
