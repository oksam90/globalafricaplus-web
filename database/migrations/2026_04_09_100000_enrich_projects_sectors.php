<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sub-categories (e.g. Agritech -> drones, irrigation, cold-chain)
        Schema::create('sub_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique(['category_id', 'slug']);
        });

        // Sustainable Development Goals (17 UN SDGs)
        Schema::create('sdgs', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('number')->unique(); // 1..17
            $table->string('name');
            $table->string('color', 7)->nullable();
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        // Pivot: projects <-> sdgs
        Schema::create('project_sdg', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sdg_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'sdg_id']);
        });

        // Project updates (news posted by the entrepreneur)
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });

        // Followers
        Schema::create('project_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        // Enrich projects
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('sub_category_id')->nullable()->after('category_id')->constrained('sub_categories')->nullOnDelete();
            $table->json('gallery')->nullable()->after('cover_image');
            $table->string('website')->nullable()->after('gallery');
            $table->string('video_url')->nullable()->after('website');
            $table->string('pitch_deck_url')->nullable()->after('video_url');
            $table->unsignedInteger('followers_count')->default(0)->after('views_count');
            $table->timestamp('published_at')->nullable()->after('deadline');
            $table->index('published_at');
            $table->index('stage');
            $table->index('amount_needed');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['sub_category_id']);
            $table->dropColumn([
                'sub_category_id', 'gallery', 'website', 'video_url',
                'pitch_deck_url', 'followers_count', 'published_at',
            ]);
        });

        Schema::dropIfExists('project_followers');
        Schema::dropIfExists('project_updates');
        Schema::dropIfExists('project_sdg');
        Schema::dropIfExists('sdgs');
        Schema::dropIfExists('sub_categories');
    }
};
