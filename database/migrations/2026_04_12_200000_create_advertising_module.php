<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bannières publicitaires (diaporama)
        Schema::create('ad_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url');
            $table->string('cta_text')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('sponsor')->nullable();          // ex: "BAD", "AfDB", "BCEAO"
            $table->string('sponsor_logo')->nullable();
            $table->enum('position', ['home_top', 'home_mid', 'sidebar'])->default('home_top');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
        });

        // Partenaires (ils nous font confiance)
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_url');
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->enum('type', ['institutional', 'financial', 'tech', 'ngo', 'government', 'media'])->default('institutional');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Témoignages
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author_name');
            $table->string('author_role');                    // ex: "Entrepreneur, Sénégal"
            $table->string('author_avatar')->nullable();
            $table->string('author_country')->nullable();
            $table->text('content');
            $table->unsignedTinyInteger('rating')->default(5); // 1-5 stars
            $table->string('project_title')->nullable();       // optional: linked project
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('ad_banners');
    }
};
