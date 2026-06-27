<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étapes 5 & 6 du contrat automatique :
 *   5. conversion .docx → PDF (LibreOffice) ;
 *   6. envoi à la signature électronique (Yousign) puis récupération du PDF signé.
 *
 * On garde la trace du PDF généré, de la demande de signature côté prestataire
 * et du document final signé.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            // 5) PDF prêt à signer (disque "local").
            $table->string('contract_pdf_path', 300)->nullable()->after('contract_path');
            // 6) Prestataire + identifiant de la demande de signature.
            $table->string('signature_provider', 30)->nullable()->after('contract_generated_at');
            $table->string('signature_request_id', 100)->nullable()->index()->after('signature_provider');
            // 6) PDF final signé par les deux parties.
            $table->string('contract_signed_path', 300)->nullable()->after('signature_request_id');
            $table->timestamp('contract_sent_at')->nullable()->after('contract_signed_path');
            $table->timestamp('contract_signed_at')->nullable()->after('contract_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn([
                'contract_pdf_path',
                'signature_provider',
                'signature_request_id',
                'contract_signed_path',
                'contract_sent_at',
                'contract_signed_at',
            ]);
        });
    }
};
