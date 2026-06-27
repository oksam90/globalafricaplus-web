<?php

namespace App\Services\Convention;

use App\Models\Investment;
use App\Models\Role;
use App\Support\MoneyToWords;
use Illuminate\Support\Carbon;

/**
 * Va chercher les VRAIES données et les met en forme pour l'injection dans la
 * convention. Produit un tableau plat clé → valeur consommé par le générateur,
 * plus une liste de jalons (tableau J1/J2/J3).
 *
 * Le mapping placeholder → clé est défini dans config/conventions.php.
 */
class ConventionContext
{
    /** Libellés des formes juridiques (cf. RoleProfile entrepreneur / formulaire projet). */
    private const LEGAL_FORMS = [
        'informal'   => 'Entreprise informelle',
        'individual' => 'Entreprise individuelle',
        'suarl'      => 'SUARL / SARLU',
        'sarl'       => 'SARL',
        'sas'        => 'SAS',
        'sa'         => 'SA',
        'gie'        => 'GIE',
        'other'      => 'Autre forme',
    ];

    /**
     * @return array{data: array<string,string>, milestones: array<int,array<string,string>>}
     */
    public function build(Investment $investment): array
    {
        $investment->loadMissing([
            'project.user.roleProfiles.role',
            'investor',
            'milestones',
        ]);

        $project  = $investment->project;
        $investor = $investment->investor;
        $owner    = $project?->user;

        $currency = strtoupper((string) ($investment->currency ?: 'EUR'));
        $amount   = (float) $investment->amount;

        // ── Profil entreprise (porteur) depuis le RoleProfile entrepreneur ──
        $entrepreneurData = [];
        if ($owner && $owner->relationLoaded('roleProfiles')) {
            $profile = $owner->roleProfiles->first(
                fn ($p) => $p->relationLoaded('role') && $p->role?->slug === 'entrepreneur'
            );
            $entrepreneurData = $profile?->data ?? [];
        }

        $companyName = $entrepreneurData['company_name'] ?? ($owner?->name ?? '—');
        $legalForm   = self::LEGAL_FORMS[$entrepreneurData['legal_status'] ?? ''] ?? '—';
        $rccm        = $entrepreneurData['registration_number'] ?? '—';
        $tax         = $entrepreneurData['tax_id'] ?? '—';

        // ── Compte de réception (séquestre) ──
        $payout = $this->payoutAccount($project);

        $dispositionDate = $investment->paid_at ? Carbon::parse($investment->paid_at) : Carbon::now();
        $contractDate    = Carbon::now();

        $data = [
            // Parties
            'investor_name'         => $investor?->name ?: '—',
            'investor_address'      => $this->address($investor?->city, $investor?->country),
            'investor_kyc_ref'      => $this->kycRef($investor),
            'company_name'          => $companyName,
            'company_legal_form'    => $legalForm,
            'company_rccm'          => $rccm,
            'company_tax'           => $tax,
            'company_address'       => $this->address($owner?->city, $owner?->country ?: $project?->country),
            'company_representative' => ($owner?->name ?: '—') . ', représentant légal',

            // Opérateur de paiement
            'operator'              => (string) config('conventions.operator', 'PayDunya'),

            // Montant
            'amount_in_words'       => MoneyToWords::figuresAndWords($amount),
            'amount_figures'        => MoneyToWords::groupThousands((int) round($amount)) . ' ' . $currency,
            'currency'              => $currency,
            'payment_means'         => 'virement / mobile money via ' . config('conventions.operator', 'PayDunya'),
            'disposition_date'      => $dispositionDate->format('d/m/Y'),

            // Compte de décaissement
            'payout_account'        => $payout,

            // Clôture
            'contract_date'         => $contractDate->format('d/m/Y'),
            'contract_place'        => $project?->city ?: config('conventions.default_place', 'Dakar'),
            'jurisdiction_law'      => (string) config('conventions.jurisdiction_law', 'OHADA'),
        ];

        $milestones = $this->milestones($investment, $currency);

        return ['data' => $data, 'milestones' => $milestones];
    }

    /**
     * Lignes du tableau des jalons (J1/J2/J3). Utilise les jalons d'escrow liés
     * à l'investissement ; à défaut, synthétise une répartition 40/40/20.
     *
     * @return array<int,array<string,string>>
     */
    private function milestones(Investment $investment, string $currency): array
    {
        $rows = [];

        $existing = $investment->relationLoaded('milestones') ? $investment->milestones : collect();
        if ($existing->isNotEmpty()) {
            foreach ($existing->take(3) as $m) {
                $rows[] = [
                    'desc'   => $m->title ?: 'Jalon',
                    'amount' => MoneyToWords::groupThousands((int) round((float) $m->amount)) . ' ' . strtoupper((string) ($m->currency ?: $currency)),
                    'date'   => $m->due_at ? Carbon::parse($m->due_at)->format('d/m/Y') : '—',
                    'proofs' => 'Preuves justificatives soumises via la Plateforme',
                ];
            }
        }

        // Complète / synthétise jusqu'à 3 lignes (40 % / 40 % / 20 %).
        if (count($rows) < 3) {
            $amount = (float) $investment->amount;
            $defaults = [
                ['Démarrage du projet', 40, Carbon::now()->addMonth()],
                ['Mi-parcours',         40, Carbon::now()->addMonths(3)],
                ['Livraison finale',    20, Carbon::now()->addMonths(6)],
            ];
            for ($i = count($rows); $i < 3; $i++) {
                [$title, $pct, $due] = $defaults[$i];
                $rows[] = [
                    'desc'   => $title,
                    'amount' => MoneyToWords::groupThousands((int) round($amount * $pct / 100)) . ' ' . $currency,
                    'date'   => $due->format('d/m/Y'),
                    'proofs' => 'Preuves justificatives soumises via la Plateforme',
                ];
            }
        }

        return array_slice($rows, 0, 3);
    }

    private function payoutAccount($project): string
    {
        if (!$project || empty($project->payout_iban)) {
            return '—';
        }
        $parts = array_filter([
            $project->payout_account_holder,
            'IBAN ' . $project->payout_iban,
            $project->payout_bic ? 'BIC ' . $project->payout_bic : null,
            $project->payout_bank_name,
        ]);
        return implode(' — ', $parts);
    }

    private function address(?string $city, ?string $country): string
    {
        $parts = array_filter([trim((string) $city), trim((string) $country)]);
        return $parts ? implode(', ', $parts) : '—';
    }

    private function kycRef($investor): string
    {
        if (!$investor) return '—';
        if (!empty($investor->kyc_verification_id)) {
            return 'GA-KYC-' . $investor->kyc_verification_id;
        }
        return 'compte vérifié #' . $investor->id;
    }
}
