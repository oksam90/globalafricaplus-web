<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liens de signature directs, par rôle.
 *
 * DocuSeal renvoie un `embed_src` par signataire. Tant que le SMTP de
 * l'instance n'est pas configuré, aucun email de demande de signature n'est
 * envoyé : ces liens sont alors le SEUL moyen pour les parties de signer.
 *
 * La colonne est masquée dans les réponses API ; chaque utilisateur ne reçoit
 * que le lien correspondant à SON rôle (cf. Investment::getMySignUrlAttribute).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            if (!Schema::hasColumn('investments', 'signature_sign_urls')) {
                $table->json('signature_sign_urls')->nullable()->after('signature_request_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            if (Schema::hasColumn('investments', 'signature_sign_urls')) {
                $table->dropColumn('signature_sign_urls');
            }
        });
    }
};
