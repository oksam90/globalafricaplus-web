<?php

namespace App\Console\Commands;

use App\Services\Payment\FeeCalculator;
use App\Services\Payment\PawaPayClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Vérifie l'environnement PawaPay : token valide, marchés activés sur le compte
 * marchand, et cohérence avec notre table de tarifs (config/pawapay.php).
 *
 * Usage :  php artisan pawapay:check
 *          php artisan pawapay:check --quote=GA:3000
 */
class PawaPayCheckCommand extends Command
{
    protected $signature = 'pawapay:check {--quote= : Simule un devis, format PAYS:MONTANT (ex. GA:3000)}';

    protected $description = 'Vérifie la configuration PawaPay (token, marchés actifs, barème de frais)';

    public function handle(PawaPayClient $client, FeeCalculator $fees): int
    {
        $this->info('── Configuration ────────────────────────────────');
        $this->line('  PSP actif   : ' . config('payments.default_gateway'));
        $this->line('  Mode        : ' . config('pawapay.mode'));
        $this->line('  Base URL    : ' . config('pawapay.base_url'));
        $this->line('  Token       : ' . (config('pawapay.api_token') ? 'présent' : 'ABSENT'));
        $this->newLine();

        $this->info('── Callback URLs à saisir dans le Dashboard ─────');
        foreach (config('pawapay.callbacks', []) as $name => $url) {
            $this->line(sprintf('  %-10s %s', ucfirst($name), $url));
        }
        $this->newLine();

        if ($quote = $this->option('quote')) {
            [$country, $amount] = array_pad(explode(':', $quote, 2), 2, null);
            $this->renderQuote($fees, (string) $country, (float) $amount);
        }

        if (!$client->isConfigured()) {
            $this->warn('PAWAPAY_API_TOKEN non renseigné — appel API ignoré.');
            $this->line('Générez-le dans Dashboard → Developers → Create API Token (après avoir enregistré les Callback URLs).');
            return self::SUCCESS;
        }

        $this->info('── Configuration active du compte marchand ──────');
        try {
            $conf = $client->activeConfiguration();
        } catch (Throwable $e) {
            $this->error('Appel GET /v2/active-conf échoué : ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->line('  Compte : ' . ($conf['companyName'] ?? '—'));

        $rows = [];
        foreach ($conf['countries'] ?? [] as $country) {
            foreach ($country['providers'] ?? [] as $provider) {
                foreach ($provider['currencies'] ?? [] as $currency) {
                    $rows[] = [
                        $country['country'] ?? '—',
                        $provider['provider'] ?? '—',
                        $currency['currency'] ?? '—',
                        data_get($currency, 'operationTypes.DEPOSIT.status', '—'),
                        data_get($currency, 'operationTypes.PAYOUT.status', '—'),
                        $this->known($provider['provider'] ?? '') ? 'oui' : 'NON',
                    ];
                }
            }
        }

        if ($rows === []) {
            $this->warn('Aucun marché activé sur ce compte.');
            return self::SUCCESS;
        }

        $this->table(['Pays', 'Opérateur', 'Devise', 'Dépôt', 'Payout', 'Tarif connu'], $rows);

        $unknown = array_filter($rows, fn ($r) => $r[5] === 'NON');
        if ($unknown !== []) {
            $this->warn(count($unknown) . ' opérateur(s) actif(s) sans barème dans config/pawapay.php → tarif de repli appliqué.');
        }

        return self::SUCCESS;
    }

    private function renderQuote(FeeCalculator $fees, string $country, float $amount): void
    {
        if ($amount <= 0) {
            $this->error('Montant invalide pour --quote (format PAYS:MONTANT).');
            return;
        }

        $this->info('── Devis simulé ────────────────────────────────');

        // Un devis par moyen de paiement : mobile money vs carte bancaire.
        foreach (array_keys($fees->availableMethods()) as $method) {
            $q = $fees->quote($country, $amount, $method, null, 'EUR');

            $this->line('  ▸ ' . strtoupper($method) . ' (' . $q->gateway . ')');
            $this->table(['Poste', 'Montant'], [
                ['Montant Reçu (porteur)', number_format($q->netAmount, 2) . ' ' . $q->currency],
                ['Commission GlobalAfrica+ (' . round($q->commissionRate * 100) . ' %)', number_format($q->commissionAmount, 2) . ' ' . $q->currency],
                ['Frais PSP — collecte', number_format($q->collectionFee, 2) . ' ' . $q->currency],
                ['Frais PSP — décaissement', number_format($q->payoutFee, 2) . ' ' . $q->currency],
                ['MONTANT ENVOYÉ (investisseur)', number_format($q->grossAmount, 2) . ' ' . $q->currency],
                ['Équivalent projet', $q->grossAmountProjectCurrency . ' ' . $q->projectCurrency],
                ['Marché', $q->countryName . ' — ' . $q->providerLabel . ' (' . $q->provider . ')'],
            ]);
        }

        $this->newLine();
    }

    /** L'opérateur a-t-il un barème explicite dans notre configuration ? */
    private function known(string $provider): bool
    {
        foreach (config('pawapay.markets', []) as $market) {
            if (isset($market['providers'][$provider])) {
                return true;
            }
        }

        return false;
    }
}
