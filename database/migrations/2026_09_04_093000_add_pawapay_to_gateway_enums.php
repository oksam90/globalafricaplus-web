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
 *
 * ⚠️ Conversion en trois temps. MySQL stocke une valeur hors ENUM comme chaîne
 * VIDE ; un `MODIFY ... ENUM(...)` direct la tronque alors et lève un warning
 * 1265 que Laravel promeut en exception — ce qui a fait échouer le déploiement
 * du 2026-09-04 sur des lignes `payment_logs` héritées. On passe donc par un
 * VARCHAR intermédiaire (aucune troncature possible), on normalise les valeurs
 * inconnues vers 'manual', puis on repose l'ENUM cible.
 *
 * Sur les autres moteurs (SQLite en test) la colonne est déjà un VARCHAR :
 * la migration est sans objet.
 */
return new class extends Migration
{
    private const ALLOWED = ['paydunya', 'pawapay', 'flutterwave', 'stripe', 'paypal', 'manual'];

    private const TABLES = ['transactions', 'payment_logs'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $enum = "'" . implode("','", self::ALLOWED) . "'";

        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'gateway')) {
                continue;
            }

            // 1. Sortir de l'ENUM : plus aucune valeur ne peut être tronquée.
            DB::statement("ALTER TABLE `{$table}` MODIFY `gateway` VARCHAR(20) NOT NULL DEFAULT 'paydunya'");

            // 2. Normaliser tout ce qui n'appartient pas à la cible (valeurs
            //    héritées invalides, stockées en chaîne vide par MySQL).
            DB::table($table)->whereNotIn('gateway', self::ALLOWED)->update(['gateway' => 'manual']);

            // 3. Reposer l'ENUM, PawaPay inclus.
            DB::statement("ALTER TABLE `{$table}` MODIFY `gateway` ENUM({$enum}) NOT NULL DEFAULT 'paydunya'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $previous = array_values(array_diff(self::ALLOWED, ['pawapay']));
        $enum     = "'" . implode("','", $previous) . "'";

        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'gateway')) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `gateway` VARCHAR(20) NOT NULL DEFAULT 'paydunya'");
            // Les lignes PawaPay seraient invalides après retour arrière :
            // on les repasse sur 'manual' plutôt que de les perdre.
            DB::table($table)->whereNotIn('gateway', $previous)->update(['gateway' => 'manual']);
            DB::statement("ALTER TABLE `{$table}` MODIFY `gateway` ENUM({$enum}) NOT NULL DEFAULT 'paydunya'");
        }
    }
};
