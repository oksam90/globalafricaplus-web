<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enrich mentorships table
        Schema::table('mentorships', function (Blueprint $table) {
            $table->foreignId('skill_id')->nullable()->after('mentee_id')->constrained()->nullOnDelete();
            $table->text('goals')->nullable()->after('message');          // What mentee wants to achieve
            $table->unsignedSmallInteger('duration_weeks')->default(8)->after('goals');
            $table->timestamp('accepted_at')->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('accepted_at');
            $table->index(['mentor_id', 'status']);
            $table->index(['mentee_id', 'status']);
        });

        // Mentor availability slots (recurring weekly)
        Schema::create('mentor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->time('start_time');
            $table->time('end_time');
            $table->string('timezone', 50)->default('Africa/Dakar');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'is_active']);
        });

        // Mentorship sessions (individual meetings within a mentorship)
        Schema::create('mentorship_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentorship_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->text('mentor_feedback')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->timestamps();
            $table->index(['mentorship_id', 'status']);
        });

        // Reviews for mentorship (after completion)
        Schema::create('mentor_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentorship_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');          // 1-5
            $table->text('comment')->nullable();
            $table->json('tags')->nullable();                // e.g. ["ponctuel","expert","patient"]
            $table->timestamps();
            $table->unique(['mentorship_id', 'reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_reviews');
        Schema::dropIfExists('mentorship_sessions');
        Schema::dropIfExists('mentor_availabilities');

        Schema::table('mentorships', function (Blueprint $table) {
            $table->dropForeign(['skill_id']);
            $table->dropColumn(['skill_id', 'goals', 'duration_weeks', 'accepted_at', 'completed_at']);
        });
    }
};
