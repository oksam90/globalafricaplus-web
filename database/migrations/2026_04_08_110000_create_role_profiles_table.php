<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->json('data')->nullable();               // role-specific fields
            $table->unsignedTinyInteger('completion')->default(0); // 0..100
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });

        // Active role preference (the "lens" under which the user views Africa+)
        Schema::table('users', function (Blueprint $table) {
            $table->string('active_role_slug', 40)->nullable()->after('preferred_language');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active_role_slug');
        });
        Schema::dropIfExists('role_profiles');
    }
};
