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

    /** Périodicités du paiement fractionné, telles que réellement calculées par InstallmentService. */
    private const FREQUENCIES = [
        'weekly'   => ['adj' => 'hebdomadaires',           'rule' => 'une échéance tous les 7 jours'],
        'biweekly' => ['adj' => 'bimensuelles',            'rule' => 'une échéance toutes les 2 semaines'],
        'monthly'  => ['adj' => 'mensuelles',              'rule' => 'une échéance par mois'],
    ];

    /**
     * @return array{
     *     data: array<string,string>,
     *     milestones: array<int,array<string,string>>,
     *     funding_schedule: array<int,array<string,string>>
     * }
     */
    public function build(Investment $investment): array
    {
        $investment->loadMissing([
            'project.user.roleProfiles.role',
            'investor',
            'milestones',
            'installmentPlan.installments',
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

        // ── Échéancier de versement (alimentation du Séquestre) ──
        // Il porte sur le MONTANT ENVOYÉ (frais PSP + commission inclus) et
        // dans la devise réellement débitée, qui peut différer de celle du
        // Montant : la convention doit dire les deux sans les confondre.
        $funding = $this->fundingSchedule($investment, $currency);

        // « Mis à disposition au plus tard le » : en versement fractionné, le
        // Séquestre n'est intégralement alimenté qu'à la dernière échéance.
        $dispositionDate = $funding['last_due']
            ?? ($investment->paid_at ? Carbon::parse($investment->paid_at) : Carbon::now());
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

            // Modalité de versement (Article 3.2) + total appelé (Annexe 1).
            'funding_mode'          => $funding['mode_label'],
            'funding_total'         => $funding['total_label'],
            'funding_note'          => $funding['note'],

            // Compte de décaissement
            'payout_account'        => $payout,

            // Clôture
            'contract_date'         => $contractDate->format('d/m/Y'),
            'contract_place'        => $project?->city ?: config('conventions.default_place', 'Dakar'),
            'jurisdiction_law'      => (string) config('conventions.jurisdiction_law', 'OHADA'),
        ];

        $milestones = $this->milestones($investment, $currency);
        $milestones = $this->alignMilestonesOnFunding($milestones, $funding['rows']);

        return [
            'data'             => $data,
            'milestones'       => $milestones,
            'funding_schedule' => $funding['rows'],
        ];
    }

    /**
     * Échéancier de versement de l'investisseur — alimentation du Séquestre.
     *
     * Le popup « Investir dans ce projet » autorise 2 à 12 échéances, en
     * périodicité hebdomadaire, bimensuelle ou mensuelle. La convention doit
     * refléter le plan réellement souscrit ; à défaut de plan, elle décrit un
     * versement unique.
     *
     * @return array{
     *     rows: array<int,array<string,string>>,
     *     mode_label: string,
     *     total_label: string,
     *     note: string,
     *     last_due: ?Carbon,
     *     cumulative: array<int,array{date: Carbon, cumulative: float, total: float}>
     * }
     */
    private function fundingSchedule(Investment $investment, string $currency): array
    {
        $plan = $investment->relationLoaded('installmentPlan') ? $investment->installmentPlan : null;

        $chargedCurrency = strtoupper((string) ($investment->charged_currency ?: $currency));
        $chargedAmount   = (float) ($investment->charged_amount ?: $investment->amount);
        $methodLabel     = $this->methodLabel(
            $plan?->payment_method ?: $investment->payment_provider
        );

        // Note commune : ce tableau porte sur le montant DÉBITÉ, pas sur le
        // Montant de l'Article 3, afin qu'aucune des deux lectures ne trompe.
        $note = 'Les montants appelés ci-dessus correspondent au montant total débité ('
            . $this->money($chargedAmount, $chargedCurrency)
            . ', frais de l\'Opérateur de paiement et commission de la Plateforme inclus). '
            . 'Le Montant porté au Séquestre au bénéfice du Projet reste celui de l\'Article 3.';

        // ── Versement unique ──
        if (!$plan || $plan->installments->isEmpty()) {
            $due = $investment->paid_at ? Carbon::parse($investment->paid_at) : Carbon::now();

            return [
                'rows' => [[
                    'number'     => '1',
                    'date'       => $due->format('d/m/Y'),
                    'amount'     => $this->money($chargedAmount, $chargedCurrency),
                    'cumulative' => $this->money($chargedAmount, $chargedCurrency),
                    'method'     => $methodLabel,
                ]],
                'mode_label'  => 'en un versement unique',
                'total_label' => $this->money($chargedAmount, $chargedCurrency),
                'note'        => $note,
                'last_due'    => $due,
                'cumulative'  => [['date' => $due, 'cumulative' => $chargedAmount, 'total' => $chargedAmount]],
            ];
        }

        // ── Versement fractionné ──
        $freq  = self::FREQUENCIES[$plan->frequency] ?? self::FREQUENCIES['monthly'];
        $count = (int) $plan->total_installments;
        $total = (float) $plan->total_amount;
        $cur   = strtoupper((string) ($plan->currency ?: $chargedCurrency));

        $rows       = [];
        $cumulative = [];
        $running    = 0.0;
        $lastDue    = null;

        foreach ($plan->installments as $installment) {
            $running += (float) $installment->amount;
            $due = $installment->due_date
                ? Carbon::parse($installment->due_date)
                : Carbon::parse($plan->starts_at ?: now());
            $lastDue = $due;

            $rows[] = [
                'number'     => (string) $installment->number,
                'date'       => $due->format('d/m/Y'),
                'amount'     => $this->money((float) $installment->amount, $cur),
                'cumulative' => $this->money($running, $cur),
                'method'     => $methodLabel,
            ];

            $cumulative[] = ['date' => $due, 'cumulative' => $running, 'total' => $total];
        }

        return [
            'rows' => $rows,
            'mode_label' => sprintf(
                'en %d échéances %s (%s), la première étant appelée à la souscription',
                $count,
                $freq['adj'],
                $freq['rule'],
            ),
            'total_label' => $this->money($total, $cur),
            'note'        => $note,
            'last_due'    => $lastDue,
            'cumulative'  => $cumulative,
        ];
    }

    /**
     * Repousse l'échéance d'un Jalon à la date où le Séquestre est censé
     * atteindre le cumul nécessaire à sa Tranche.
     *
     * Sans ce garde-fou, une convention pouvait promettre 40 % du Montant au
     * bout d'un mois alors que l'investisseur règle en douze échéances
     * mensuelles : à cette date, moins d'un douzième est encaissé. La règle est
     * la traduction de l'Article 6.2 (subordination aux fonds encaissés).
     *
     * @param  array<int,array<string,string>>  $milestones
     * @param  array<int,array<string,string>>  $schedule
     * @return array<int,array<string,string>>
     */
    private function alignMilestonesOnFunding(array $milestones, array $schedule): array
    {
        if (count($schedule) < 2) {
            return $milestones;
        }

        // Part cumulée du financement à chaque échéance, en fraction du total.
        // Les échéances sont d'égal montant (le reliquat est absorbé par la
        // dernière), la part cumulée est donc bien i/n.
        $steps = [];
        $n     = count($schedule);
        foreach ($schedule as $i => $row) {
            $steps[] = ['date' => $row['date'], 'share' => ($i + 1) / $n];
        }

        $aligned = [];

        foreach ($milestones as $i => $row) {
            // Part cumulée que représente cette Tranche : lue sur les jalons
            // eux-mêmes, et non figée à 40/40/20, pour rester juste quand le
            // porteur a défini ses propres pourcentages.
            $needed   = (float) ($row['cumulative_share'] ?? 1.0);
            $earliest = null;
            foreach ($steps as $step) {
                if ($step['share'] >= $needed - 0.0001) {
                    $earliest = $step['date'];
                    break;
                }
            }

            if ($earliest && $this->isBefore($row['date'], $earliest)) {
                $row['date'] = $earliest;
            }

            $aligned[] = $row;
        }

        return $aligned;
    }

    /**
     * Compare deux dates au format d/m/Y. Une date absente (« — ») est traitée
     * comme antérieure : elle doit être remplacée par la date de financement.
     */
    private function isBefore(string $a, string $b): bool
    {
        $pattern = '/^\d{2}\/\d{2}\/\d{4}$/';

        if (!preg_match($pattern, $a)) {
            return true;
        }
        if (!preg_match($pattern, $b)) {
            return false;
        }

        return Carbon::createFromFormat('d/m/Y', $a)->lt(Carbon::createFromFormat('d/m/Y', $b));
    }

    private function money(float $amount, string $currency): string
    {
        return MoneyToWords::groupThousands((int) round($amount)) . ' ' . strtoupper($currency);
    }

    /** Libellé lisible du moyen de paiement (mobile_money → « Mobile Money »). */
    private function methodLabel(?string $method): string
    {
        if (!$method) {
            return 'Moyen choisi lors de la souscription';
        }

        $label = config("payments.methods.{$method}.label");
        if ($label) {
            return (string) $label;
        }

        return match (strtolower($method)) {
            'pawapay'  => 'Mobile Money',
            'paydunya' => 'Carte bancaire',
            default    => ucfirst($method),
        };
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

        $total = (float) $investment->amount;

        $existing = $investment->relationLoaded('milestones') ? $investment->milestones : collect();
        if ($existing->isNotEmpty()) {
            foreach ($existing->take(3) as $m) {
                $rows[] = [
                    'desc'   => $m->title ?: 'Jalon',
                    'amount' => MoneyToWords::groupThousands((int) round((float) $m->amount)) . ' ' . strtoupper((string) ($m->currency ?: $currency)),
                    'date'   => $m->due_at ? Carbon::parse($m->due_at)->format('d/m/Y') : '—',
                    'proofs' => 'Preuves justificatives soumises via la Plateforme',
                    'raw'    => (float) $m->amount,
                ];
            }
        }

        // Complète / synthétise jusqu'à 3 lignes (40 % / 40 % / 20 %).
        if (count($rows) < 3) {
            $defaults = [
                ['Démarrage du projet', 40, Carbon::now()->addMonth()],
                ['Mi-parcours',         40, Carbon::now()->addMonths(3)],
                ['Livraison finale',    20, Carbon::now()->addMonths(6)],
            ];
            for ($i = count($rows); $i < 3; $i++) {
                [$title, $pct, $due] = $defaults[$i];
                $rows[] = [
                    'desc'   => $title,
                    'amount' => MoneyToWords::groupThousands((int) round($total * $pct / 100)) . ' ' . $currency,
                    'date'   => $due->format('d/m/Y'),
                    'proofs' => 'Preuves justificatives soumises via la Plateforme',
                    'raw'    => $total * $pct / 100,
                ];
            }
        }

        $rows = array_slice($rows, 0, 3);

        // Part cumulée de chaque Tranche, servant à vérifier que le Séquestre
        // est suffisamment approvisionné à l'échéance du Jalon (Article 6.3).
        $sum     = array_sum(array_column($rows, 'raw')) ?: $total;
        $running = 0.0;
        foreach ($rows as $i => $row) {
            $running += (float) $row['raw'];
            $rows[$i]['cumulative_share'] = $sum > 0 ? min(1.0, $running / $sum) : 1.0;
            unset($rows[$i]['raw']);
        }

        return $rows;
    }

    /**
     * Compte de réception (séquestre) tel qu'il figure dans la convention.
     *
     * Deux canaux possibles, le mobile money étant le canal principal depuis
     * l'intégration PawaPay et le virement bancaire le canal secondaire
     * (paiements par carte). Les deux sont restitués quand ils sont renseignés,
     * afin que la convention reflète exactement les moyens de décaissement
     * ouverts au porteur de projet.
     */
    private function payoutAccount($project): string
    {
        if (!$project) {
            return '—';
        }

        $channels = [];

        if (!empty($project->payout_mobile_number) && !empty($project->payout_mobile_provider)) {
            $channels[] = implode(' — ', array_filter([
                $project->payout_mobile_holder ?: $project->payout_account_holder,
                'Mobile Money ' . $this->providerLabel($project->payout_mobile_provider),
                $project->payout_mobile_number,
            ]));
        }

        if (!empty($project->payout_iban)) {
            $channels[] = implode(' — ', array_filter([
                $project->payout_account_holder,
                'IBAN ' . $project->payout_iban,
                $project->payout_bic ? 'BIC ' . $project->payout_bic : null,
                $project->payout_bank_name,
            ]));
        }

        return $channels ? implode(' / ', $channels) : '—';
    }

    /** Libellé lisible d'un code opérateur PawaPay (AIRTEL_GAB → Airtel Money). */
    private function providerLabel(string $code): string
    {
        foreach (config('pawapay.markets', []) as $market) {
            if (isset($market['providers'][$code]['label'])) {
                return (string) $market['providers'][$code]['label'];
            }
        }

        return $code;
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
