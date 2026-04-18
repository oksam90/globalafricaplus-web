<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend users with Africa+ specific fields
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('country')->nullable()->after('phone');
            $table->string('city')->nullable()->after('country');
            $table->string('avatar')->nullable()->after('city');
            $table->text('bio')->nullable()->after('avatar');
            $table->enum('kyc_level', ['none', 'basic', 'verified', 'certified'])->default('none')->after('bio');
            $table->boolean('is_diaspora')->default(false)->after('kyc_level');
            $table->string('residence_country')->nullable()->after('is_diaspora');
            $table->string('preferred_language', 5)->default('fr')->after('residence_country');
        });

        // Roles (RBAC light)
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // entrepreneur, investor, government, jobseeker, mentor, admin
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });

        // Sectors / categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();
        });

        // Projects
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary');
            $table->longText('description')->nullable();
            $table->string('country');
            $table->string('city')->nullable();
            $table->decimal('amount_needed', 14, 2)->default(0);
            $table->decimal('amount_raised', 14, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->enum('stage', ['idea', 'mvp', 'launch', 'scaling'])->default('idea');
            $table->enum('status', ['draft', 'pending', 'published', 'funded', 'closed', 'rejected'])->default('draft');
            $table->unsignedInteger('jobs_target')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->string('cover_image')->nullable();
            $table->json('tags')->nullable();
            $table->date('deadline')->nullable();
            $table->timestamps();
            $table->index(['country', 'status']);
            $table->index(['category_id', 'status']);
        });

        // Investments / contributions
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investor_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('EUR');
            $table->enum('type', ['equity', 'donation', 'loan', 'reward'])->default('equity');
            $table->enum('status', ['pending', 'escrow', 'released', 'refunded', 'failed'])->default('pending');
            $table->string('payment_provider')->nullable(); // stripe, flutterwave, mobile_money
            $table->string('provider_reference')->nullable();
            $table->timestamps();
        });

        // Skills marketplace
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->timestamps();
        });

        Schema::create('skill_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->enum('level', ['beginner', 'intermediate', 'advanced', 'expert'])->default('intermediate');
            $table->unsignedInteger('years_experience')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'skill_id']);
        });

        // Mentorship requests
        Schema::create('mentorships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentee_id')->constrained('users')->cascadeOnDelete();
            $table->string('topic');
            $table->text('message')->nullable();
            $table->enum('status', ['requested', 'accepted', 'declined', 'completed'])->default('requested');
            $table->timestamps();
        });

        // Government calls for projects
        Schema::create('government_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // ministry user
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('country');
            $table->string('sector')->nullable();
            $table->decimal('budget', 14, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->date('opens_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->enum('status', ['draft', 'open', 'closed', 'awarded'])->default('draft');
            $table->timestamps();
        });

        // Reviews / trust
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_user_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('reviewable'); // project, mentorship...
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        // KYC documents
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('document_type', ['id_card', 'passport', 'driver_license', 'selfie', 'proof_address']);
            $table->string('file_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reject_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('government_calls');
        Schema::dropIfExists('mentorships');
        Schema::dropIfExists('skill_user');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('investments');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'country', 'city', 'avatar', 'bio',
                'kyc_level', 'is_diaspora', 'residence_country', 'preferred_language',
            ]);
        });
    }
};
