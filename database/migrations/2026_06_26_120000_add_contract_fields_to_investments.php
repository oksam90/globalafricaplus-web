<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Génération automatique de la convention d'investissement.
 *
 * Au moment où un investissement est confirmé (paiement validé), le système
 * fabrique le bon contrat (selon `investments.type`) déjà rempli avec les vraies
 * données. On garde la trace du document produit et de son cycle de vie ici.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            // Gabarit retenu (= type au moment de la génération : equity|donation|loan|reward).
            $table->string('contract_type', 20)->nullable()->after('type');
            // Chemin du .docx généré (disque "local").
            $table->string('contract_path', 300)->nullable()->after('contract_type');
            // Cycle de vie : none → generated → sent → signed (ou failed).
            $table->enum('contract_status', ['none', 'generated', 'sent', 'signed', 'failed'])
                ->default('none')->after('contract_path');
            $table->timestamp('contract_generated_at')->nullable()->after('contract_status');
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['contract_type', 'contract_path', 'contract_status', 'contract_generated_at']);
        });
    }
};
