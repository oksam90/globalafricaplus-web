<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Services\Convention\ConventionSignatureService;
use App\Services\Convention\DocuSealClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Vérifie l'environnement de signature électronique : prestataire actif,
 * joignabilité de l'instance DocuSeal, jeton API, et URL de webhook à saisir
 * dans le tableau de bord.
 *
 * Usage :  php artisan docuseal:check
 *          php artisan docuseal:check --investment=12
 */
class DocuSealCheckCommand extends Command
{
    protected $signature = 'docuseal:check {--investment= : Vérifie le statut de signature d\'un investissement}';

    protected $description = 'Vérifie la configuration DocuSeal (jeton, instance, webhook)';

    public function handle(DocuSealClient $client, ConventionSignatureService $signatures): int
    {
        $this->info('── Signature électronique ───────────────────────');
        $this->line('  Prestataire actif : ' . config('signature.provider'));
        $this->line('  Envoi auto        : ' . (config('signature.auto_send') ? 'oui' : 'non'));
        $this->newLine();

        $this->info('── DocuSeal ─────────────────────────────────────');
        $this->line('  Instance   : ' . config('docuseal.base_url'));
        $this->line('  Jeton API  : ' . (filled(config('docuseal.api_token')) ? 'présent' : 'ABSENT'));
        $this->line('  Emails     : ' . (config('docuseal.send_email')
            ? 'envoyés par DocuSeal (SMTP requis)'
            : 'désactivés — liens de signature affichés dans l\'application'));
        $this->line('  Ordre      : ' . config('docuseal.order'));
        $this->newLine();

        $this->info('── URL de webhook à saisir dans DocuSeal ────────');
        // L'URL ne doit porter AUCUN secret : l'authenticité est établie par
        // l'en-tête `X-Docuseal-Signature` (HMAC-SHA256). Un jeton en query
        // string finirait dans les journaux d'accès et dans l'écran de
        // configuration de DocuSeal.
        $this->line('  ' . rtrim((string) config('app.url'), '/') . '/api/v1/webhooks/docuseal');
        $this->line('  Événements : form.completed, form.declined, submission.completed, submission.expired');
        $this->line('  Signature  : ' . (filled(config('docuseal.webhook_secret'))
            ? 'HMAC vérifiée (secret configuré)'
            : 'NON VÉRIFIÉE — renseignez DOCUSEAL_WEBHOOK_SECRET'));
        $this->newLine();

        if (!$client->isConfigured()) {
            $this->warn('DOCUSEAL_API_TOKEN non renseigné — appel API ignoré.');
            $this->line('Copiez le jeton depuis ' . config('docuseal.base_url') . '/settings/api');

            return self::SUCCESS;
        }

        $this->info('── Connexion à l\'instance ───────────────────────');
        try {
            $client->ping();
            $this->line('  ✔ Jeton accepté, API joignable.');
        } catch (Throwable $e) {
            $this->error('  ✘ ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($id = $this->option('investment')) {
            $this->checkInvestment((int) $id, $signatures);
        }

        return self::SUCCESS;
    }

    private function checkInvestment(int $id, ConventionSignatureService $signatures): void
    {
        $this->newLine();
        $this->info('── Investissement #' . $id . ' ─────────────────────');

        $investment = Investment::find($id);
        if (!$investment) {
            $this->error('  Investissement introuvable.');

            return;
        }

        $this->table(['Champ', 'Valeur'], [
            ['Statut investissement', $investment->status],
            ['Convention (docx)', $investment->has_contract ? 'oui' : 'non'],
            ['Convention (pdf)', $investment->has_contract_pdf ? 'oui' : 'non'],
            ['Statut signature', $investment->contract_status ?: '—'],
            ['Prestataire', $investment->signature_provider ?: '—'],
            ['Demande', $investment->signature_request_id ?: '—'],
            ['Signée', $investment->has_signed_contract ? 'oui' : 'non'],
        ]);

        if (!$investment->signature_request_id) {
            $this->line('  Aucune demande envoyée pour cet investissement.');

            return;
        }

        try {
            $raw = $signatures->syncStatus($investment);
            $this->line('  Statut prestataire : ' . $raw);
            $this->line('  Statut après sync  : ' . $investment->fresh()->contract_status);
        } catch (Throwable $e) {
            $this->error('  Synchronisation impossible : ' . $e->getMessage());
        }
    }
}
