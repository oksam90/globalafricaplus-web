<?php

namespace App\Services\Payment\DTOs;

/**
 * Décomposition complète d'un investissement, du « Montant Reçu » (net touché
 * par le porteur de projet) au « Montant Envoyé » (débit total de l'investisseur).
 *
 *   Montant Envoyé = Montant Reçu
 *                  + commission GlobalAfrica+ (barème 3 % / 2 % / 1 %)
 *                  + frais PawaPay de collecte  (pays du projet)
 *                  + frais PawaPay de décaissement (pays du projet)
 *
 * Toutes les valeurs monétaires sont exprimées dans `currency` (devise mobile
 * money du marché, ex. XAF au Gabon), sauf les champs `*ProjectCurrency`.
 */
class FeeQuote
{
    public function __construct(
        public readonly float  $netAmount,             // Montant Reçu (porteur)
        public readonly float  $grossAmount,           // Montant Envoyé (investisseur)
        public readonly float  $commissionAmount,      // Commission plateforme
        public readonly float  $commissionRate,        // 0.03 | 0.02 | 0.01
        public readonly float  $collectionFee,         // Frais PawaPay — encaissement
        public readonly float  $payoutFee,             // Frais PawaPay — décaissement
        public readonly string $currency,              // Devise de règlement (XAF, XOF…)
        public readonly string $country,               // ISO-2 du projet
        public readonly string $countryName,
        public readonly string $provider,              // Code opérateur / "card"
        public readonly string $providerLabel,
        public readonly string $method = 'mobile_money', // mobile_money | card
        public readonly string $gateway = 'pawapay',     // pawapay | paydunya
        public readonly float  $customerOperatorFee = 0.0, // Prélevé par l'opérateur au payeur (info)
        public readonly ?float $netAmountProjectCurrency = null,
        public readonly ?float $grossAmountProjectCurrency = null,
        public readonly ?string $projectCurrency = null,
    ) {}

    /** Total des frais PSP supportés par l'investisseur. */
    public function providerFees(): float
    {
        return round($this->collectionFee + $this->payoutFee, 6);
    }

    /** Total « frais + commission » supporté par l'investisseur. */
    public function totalCharges(): float
    {
        return round($this->grossAmount - $this->netAmount, 6);
    }

    /** Payload JSON consommé par le popup « Investir dans ce projet ». */
    public function toArray(): array
    {
        return [
            'method'                   => $this->method,
            'gateway'                  => $this->gateway,
            'method_label'             => config("payments.methods.{$this->method}.label", $this->method),
            'country'                  => $this->country,
            'country_name'             => $this->countryName,
            'provider'                 => $this->provider,
            'provider_label'           => $this->providerLabel,
            'currency'                 => $this->currency,

            'net_amount'               => $this->netAmount,
            'gross_amount'             => $this->grossAmount,

            'commission_rate'          => $this->commissionRate,
            'commission_amount'        => $this->commissionAmount,

            'provider_fees'            => $this->providerFees(),
            'provider_fee_collection'  => $this->collectionFee,
            'provider_fee_payout'      => $this->payoutFee,

            'customer_operator_fee'    => $this->customerOperatorFee,
            'total_charges'            => $this->totalCharges(),

            'project_currency'             => $this->projectCurrency,
            'net_amount_project_currency'  => $this->netAmountProjectCurrency,
            'gross_amount_project_currency'=> $this->grossAmountProjectCurrency,

            // Le barème est public, PAS les montants pivots.
            'commission_label'         => config('payments.commission.public_label'),
        ];
    }
}
