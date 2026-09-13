<?php

namespace App\Console\Commands;

use App\Services\Convention\DocuSealClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Liste les gabarits DocuSeal, leurs rôles et le NOM EXACT de leurs champs,
 * puis confronte le tout aux clés attendues par le préremplissage.
 *
 * Sert à câbler `config/docuseal.templates` (mode « gabarit », édition libre) :
 * un champ dont le nom ne correspond à aucune clé du contexte ne sera jamais
 * prérempli, et l'erreur est silencieuse côté DocuSeal.
 */
class DocuSealTemplatesCommand extends Command
{
    protected $signature = 'docuseal:templates';

    protected $description = 'Liste les gabarits DocuSeal et vérifie le nom de leurs champs';

    /** Clés produites par ConventionContext::build(), aplaties. */
    private const CONTEXT_KEYS = [
        'investor_name', 'investor_address', 'investor_kyc_ref',
        'company_name', 'company_legal_form', 'company_rccm', 'company_tax',
        'company_address', 'company_representative',
        'operator', 'amount_in_words', 'amount_figures', 'currency',
        'payment_means', 'disposition_date', 'payout_account',
        'contract_date', 'contract_place', 'jurisdiction_law',
        // Modalité de versement (Article 3.2) + total appelé (Annexe 1).
        'funding_mode', 'funding_total', 'funding_note',
        'jalon_1_desc', 'jalon_1_montant', 'jalon_1_date',
        'jalon_2_desc', 'jalon_2_montant', 'jalon_2_date',
        'jalon_3_desc', 'jalon_3_montant', 'jalon_3_date',
    ];

    /**
     * Échéancier de versement : le gabarit a une mise en page fixe, il déroule
     * donc les 12 lignes du maximum autorisé (ech_1_* à ech_12_*). Les lignes
     * non utilisées restent vides — elles ne doivent jamais être obligatoires.
     *
     * @return list<string>
     */
    private static function contextKeys(): array
    {
        $keys = self::CONTEXT_KEYS;

        for ($n = 1; $n <= 12; $n++) {
            $keys[] = "ech_{$n}_date";
            $keys[] = "ech_{$n}_montant";
            $keys[] = "ech_{$n}_cumul";
        }

        return $keys;
    }

    public function handle(DocuSealClient $client): int
    {
        if (!$client->isConfigured()) {
            $this->error('DOCUSEAL_API_TOKEN non renseigné.');

            return self::FAILURE;
        }

        try {
            $templates = $client->listTemplates()['data'] ?? [];
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($templates === []) {
            $this->warn('Aucun gabarit. Créez-en un par type de convention dans '
                . config('docuseal.base_url') . ' puis relancez cette commande.');

            return self::SUCCESS;
        }

        $configured = array_filter((array) config('docuseal.templates'));

        foreach ($templates as $template) {
            $id    = $template['id'] ?? '?';
            $types = array_keys($configured, (string) $id) ?: array_keys($configured, (int) $id);

            $this->newLine();
            $this->info("── Gabarit #{$id} — « " . ($template['name'] ?? '?') . " »");
            $this->line('  Rôles  : ' . implode(', ', array_column($template['submitters'] ?? [], 'name')));
            $this->line('  Câblé  : ' . ($types ? implode(', ', $types) : 'non — à reporter dans DOCUSEAL_TEMPLATE_*'));

            $rows = [];
            foreach ($template['fields'] ?? [] as $field) {
                $name = (string) ($field['name'] ?? '');
                $rows[] = [
                    $name,
                    $field['type'] ?? '?',
                    match (true) {
                        in_array($field['type'] ?? '', ['signature', 'initials', 'date'], true) => '—',
                        in_array($name, self::contextKeys(), true) => '✔ prérempli',
                        default => '✘ nom inconnu',
                    },
                ];
            }

            if ($rows === []) {
                $this->warn('  Aucun champ défini — le document ne pourra pas être signé.');

                continue;
            }

            $this->table(['Champ', 'Type', 'Préremplissage'], $rows);

            $missing = array_diff(
                self::contextKeys(),
                array_column($template['fields'] ?? [], 'name'),
            );
            if ($missing !== []) {
                $this->line('  Clés disponibles non utilisées : ' . implode(', ', $missing));
            }
        }

        $this->newLine();
        $this->info('Reportez les identifiants dans le .env :');
        foreach (array_keys((array) config('docuseal.templates')) as $type) {
            $this->line('  DOCUSEAL_TEMPLATE_' . strtoupper($type) . '=');
        }

        return self::SUCCESS;
    }
}
