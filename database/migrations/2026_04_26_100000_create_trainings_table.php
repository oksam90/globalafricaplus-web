<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 5 — Catalogue de formations payantes (modules en ligne).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // formateur
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('video_preview_url')->nullable();
            $table->string('content_url')->nullable(); // lien d'accès débloqué après paiement
            $table->string('category', 80)->nullable()->index();
            $table->string('level', 30)->nullable(); // beginner|intermediate|advanced
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->json('curriculum')->nullable();
            $table->decimal('price', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->boolean('is_published')->default(false)->index();
            $table->unsignedInteger('purchases_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
