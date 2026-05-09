<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-05-09 — Add Google OAuth identifier to users.
 *
 *   google_id        : the `sub` claim from the Google ID token. Unique per
 *                      Google account, immutable, indexed for the lookup
 *                      that happens on every Sign-in-with-Google round.
 *   oauth_provider   : nullable, set to `google` once the user signs in via
 *                      OAuth at least once. Helps reporting + future
 *                      multi-provider expansion (Facebook / Apple / GitHub).
 *
 * The existing `password` column is kept nullable so Socialite-only users
 * (who never set a password) can authenticate solely via OAuth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id', 50)->nullable()->unique()->after('email');
            $table->string('oauth_provider', 20)->nullable()->after('google_id');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'oauth_provider']);
            // Note: not restoring password to NOT NULL — would break OAuth-only users.
        });
    }
};
