<?php

namespace Tests\Feature\Payment;

use App\Services\Payment\FeeCalculator;
use Tests\TestCase;

/**
 * Verrouille les règles métier de tarification :
 *  - l'investisseur supporte 100 % des frais ;
 *  - le porteur de projet encaisse EXACTEMENT le « Montant Reçu » ;
 *  - la commission suit le barème dégressif 3 % / 2 % / 1 %.
 */
class FeeCalculatorTest extends TestCase
{
    private function calc(): FeeCalculator
    {
        return app(FeeCalculator::class);
    }

    /**
     * Cas de la spécification : projet au Gabon, 3 000 FCFA reçus par le porteur.
     * Montant Envoyé = 3 000 + commission 3 % + frais PawaPay Gabon.
     */
    public function test_gabon_3000_xaf_matches_the_business_example(): void
    {
        $quote = $this->calc()->quote('GA', 3000.0);

        $this->assertSame('XAF', $quote->currency);
        $this->assertSame('AIRTEL_GAB', $quote->provider);
        $this->assertSame(3000.0, $quote->netAmount);

        // Palier 1 : 3 000 XAF ≈ 4,57 EUR ≤ pivot 5 EUR → 3 %
        $this->assertSame(0.03, $quote->commissionRate);
        $this->assertSame(90.0, $quote->commissionAmount);

        // Décaissement Airtel Gabon : 1 %
        $this->assertSame(30.0, $quote->payoutFee);

        // Le montant envoyé couvre strictement net + commission + frais.
        $this->assertGreaterThan($quote->netAmount, $quote->grossAmount);
        $this->assertEqualsWithDelta(
            $quote->grossAmount,
            $quote->netAmount + $quote->commissionAmount + $quote->providerFees(),
            0.01,
        );
    }

    /**
     * Le gross-up doit garantir qu'après prélèvement des frais de collecte, le
     * wallet contient de quoi payer le porteur, le décaissement ET la commission.
     */
    public function test_gross_up_covers_payout_and_commission_after_collection_fees(): void
    {
        foreach ([['GA', 3000], ['SN', 25000], ['CM', 10000], ['CI', 100000]] as [$country, $net]) {
            $quote = $this->calc()->quote($country, (float) $net);

            $wallet   = $quote->grossAmount - $quote->collectionFee;
            $required = $quote->netAmount + $quote->payoutFee + $quote->commissionAmount;

            $this->assertGreaterThanOrEqual(
                $required - 0.01,
                $wallet,
                "Gross-up insuffisant pour {$country} / {$net}",
            );
        }
    }

    /**
     * Barème officiel (capture « 4. Barème de commission de la plateforme ») :
     *   ≥      5 €  →  3,00 %
     *   ≥  5 000 €  →  2,00 %
     *   ≥ 20 000 €  →  1,00 %
     * Les pivots sont des bornes INCLUSIVES basses.
     */
    public function test_commission_scale_matches_official_tiers(): void
    {
        $calc = $this->calc();

        $this->assertSame(0.03, $calc->commissionRate(5.0, 'EUR'));
        $this->assertSame(0.03, $calc->commissionRate(4999.0, 'EUR'));
        $this->assertSame(0.02, $calc->commissionRate(5000.0, 'EUR'));
        $this->assertSame(0.02, $calc->commissionRate(19999.0, 'EUR'));
        $this->assertSame(0.01, $calc->commissionRate(20000.0, 'EUR'));
        $this->assertSame(0.01, $calc->commissionRate(100000.0, 'EUR'));

        // Même barème via la devise locale (3 000 FCFA ≈ 4,57 EUR → 3 %).
        $this->assertSame(0.03, $calc->commissionRate(3000.0, 'XAF'));
    }

    /** Les deux moyens de paiement produisent un devis cohérent. */
    public function test_card_and_mobile_money_both_quote(): void
    {
        $calc = $this->calc();

        $mm   = $calc->quote('GA', 3000.0, FeeCalculator::METHOD_MOBILE_MONEY);
        $card = $calc->quote('GA', 3000.0, FeeCalculator::METHOD_CARD);

        $this->assertSame('pawapay', $mm->gateway);
        $this->assertSame('paydunya', $card->gateway);
        $this->assertSame('XAF', $card->currency);

        // Même net, même commission — seuls les frais PSP diffèrent.
        $this->assertSame($mm->netAmount, $card->netAmount);
        $this->assertSame($mm->commissionAmount, $card->commissionAmount);

        // La carte bancaire (3,50 %) coûte plus cher que le mobile money (2 %).
        $this->assertGreaterThan($mm->grossAmount, $card->grossAmount);

        foreach ([$mm, $card] as $q) {
            $wallet = $q->grossAmount - $q->collectionFee;
            $this->assertGreaterThanOrEqual(
                $q->netAmount + $q->payoutFee + $q->commissionAmount - 0.01,
                $wallet,
            );
        }
    }

    /**
     * Régression : un nom de pays non normalisé (« Sénégal ») était écrit tel
     * quel dans `transactions.customer_country` (char(2)) et provoquait un
     * SQLSTATE[22001] « Data too long » en production.
     */
    public function test_country_names_normalise_to_two_ascii_letters(): void
    {
        $calc = $this->calc();

        foreach (['Sénégal', 'SÉNÉGAL', 'Côte d\'Ivoire', 'Cameroun', 'Gabon', 'Burkina Faso', 'inconnu', '', null] as $raw) {
            $iso = $calc->normalizeCountry($raw);

            $this->assertSame(2, strlen($iso), "Code trop long pour « {$raw} » : {$iso}");
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $iso);
        }

        $this->assertSame('SN', $calc->normalizeCountry('Sénégal'));
        $this->assertSame('CI', $calc->normalizeCountry('Côte d\'Ivoire'));
        $this->assertSame('CM', $calc->normalizeCountry('Cameroun'));
    }

    /** Le barème public ne doit JAMAIS exposer les montants pivots. */
    public function test_public_scale_hides_pivot_amounts(): void
    {
        $scale = $this->calc()->publicCommissionScale();

        $this->assertSame([0.03, 0.02, 0.01], $scale['rates']);
        $this->assertArrayNotHasKey('tiers', $scale);
        $this->assertStringNotContainsString('5', (string) ($scale['label'] ?? ''));
    }

    /** Les devises sans sous-unité (XOF/XAF) sont arrondies à l'entier. */
    public function test_zero_decimal_currencies_are_rounded_to_units(): void
    {
        $quote = $this->calc()->quote('SN', 12345.0);

        $this->assertSame($quote->grossAmount, floor($quote->grossAmount));
        $this->assertSame($quote->commissionAmount, floor($quote->commissionAmount));
    }

    /**
     * Un moyen de paiement dont le PSP n'a pas d'identifiants ne doit jamais
     * être proposé : sinon un environnement configuré pour un seul PSP
     * afficherait les deux options et échouerait au moment du paiement.
     */
    public function test_methods_without_credentials_are_not_offered(): void
    {
        // PawaPay non configuré → seule la carte bancaire est proposée.
        config(['pawapay.api_token' => null, 'paydunya.master_key' => 'test-key']);
        $calc = app(FeeCalculator::class);

        $this->assertSame(['card'], array_keys($calc->availableMethods()));
        $this->assertSame('card', $calc->defaultMethod());
        $this->assertSame('paydunya', $calc->quote('SN', 5000.0)->gateway);

        // Inversement, PayDunya non configuré → seul le mobile money reste.
        config(['pawapay.api_token' => 'test-token', 'paydunya.master_key' => null]);

        $this->assertSame(['mobile_money'], array_keys($calc->availableMethods()));
        $this->assertSame('pawapay', $calc->quote('SN', 5000.0)->gateway);

        // Les deux configurés → les deux proposés.
        config(['pawapay.api_token' => 'test-token', 'paydunya.master_key' => 'test-key']);

        $this->assertSame(['mobile_money', 'card'], array_keys($calc->availableMethods()));
    }

    /** Un pays hors marchés PawaPay retombe sur le marché par défaut. */
    public function test_unknown_country_falls_back_to_default_market(): void
    {
        $quote = $this->calc()->quote('FR', 10000.0);

        $this->assertSame(config('pawapay.default_market', 'SN'), $quote->country);
    }
}
