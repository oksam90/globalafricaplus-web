<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `transactions.gateway` et `payment_logs.gateway` sont des ENUM MySQL créés
 * avant l'intégration PawaPay : toute insertion avec gateway = 'pawapay'
 * échouait en « Data truncated for column 'gateway' ».
 *
 * On étend l'ENUM sans toucher aux valeurs existantes (PayDunya reste actif).
 * Sur les autres moteurs (SQLite en test) la colonne est déjà un VARCHAR :
 * la migration est alors sans objet.
 */
return new class extends Migration
{
    private const VALUES = "'paydunya','pawapay','flutterwave','stripe','paypal','manual'";

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (['transactions', 'payment_logs'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'gateway')) {
                DB::statement(
                    "ALTER TABLE `{$table}` MODIFY `gateway` ENUM(" . self::VALUES . ") NOT NULL DEFAULT 'paydunya'"
                );
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Les lignes PawaPay existantes seraient invalides après retour arrière :
        // on les repasse sur 'manual' plutôt que de les perdre.
        foreach (['transactions', 'payment_logs'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'gateway')) {
                DB::table($table)->where('gateway', 'pawapay')->update(['gateway' => 'manual']);
                DB::statement(
                    "ALTER TABLE `{$table}` MODIFY `gateway` ENUM('paydunya','flutterwave','stripe','paypal','manual') NOT NULL DEFAULT 'paydunya'"
                );
            }
        }
    }
};
