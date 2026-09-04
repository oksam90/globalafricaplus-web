<?php

namespace Tests\Feature\Payment;

use App\Models\Investment;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\InstallmentService;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Régression 2026-09-04 : un investisseur ayant choisi « Carte bancaire » puis
 * coché « Étaler en plusieurs paiements » était redirigé vers PawaPay à la 1re
 * échéance. `InstallmentService::invoiceNext()` ignorait le moyen retenu et
 * retombait sur le PSP par défaut du pays.
 */
class InstallmentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Les deux PSP doivent être « configurés » pour que les deux moyens
        // soient proposables (cf. FeeCalculator::availableMethods()).
        config([
            'pawapay.api_token'    => 'test-token',
            'paydunya.master_key'  => 'test-key',
        ]);
    }

    public function test_gateway_factory_maps_each_method_to_its_psp(): void
    {
        $factory = app(PaymentGatewayFactory::class);

        $this->assertSame('paydunya', $factory->forMethod('card')->getName());
        $this->assertSame('pawapay', $factory->forMethod('mobile_money')->getName());
    }

    public function test_installment_plan_keeps_the_chosen_payment_method(): void
    {
        [$user, $investment] = $this->makeInvestment();

        $plan = app(InstallmentService::class)->createPlan(
            user: $user,
            payable: $investment,
            totalAmount: 300000.0,
            currency: 'XOF',
            totalInstallments: 3,
            frequency: 'monthly',
            startsAt: null,
            paymentMethod: 'card',
        );

        $this->assertSame('card', $plan->payment_method);
        $this->assertSame(3, $plan->installments()->count());

        // Le PSP dérivé du plan doit être PayDunya, pas le défaut du pays.
        $this->assertSame(
            'paydunya',
            app(PaymentGatewayFactory::class)->forMethod($plan->payment_method)->getName(),
        );
    }

    /**
     * Le plan doit porter le MONTANT ENVOYÉ (frais + commission inclus), sinon
     * les échéances ne collectent que le montant reçu par le porteur et la
     * plateforme n'encaisse jamais ses frais.
     */
    public function test_plan_total_covers_fees_not_only_the_net_amount(): void
    {
        [$user, $investment] = $this->makeInvestment();

        $plan = app(InstallmentService::class)->createPlan(
            user: $user,
            payable: $investment,
            totalAmount: (float) $investment->charged_amount,
            currency: $investment->charged_currency,
            totalInstallments: 3,
        );

        $this->assertGreaterThan((float) $investment->amount, (float) $plan->total_amount);

        // La somme des échéances doit être EXACTEMENT le montant envoyé : en
        // XOF/XAF (devises sans sous-unité), un arrondi par échéance faisait
        // auparavant perdre jusqu'à n-1 unités au total encaissé.
        $this->assertSame(
            (float) $investment->charged_amount,
            (float) $plan->installments()->sum('amount'),
        );
    }

    /** @return array{0:User,1:Investment} */
    private function makeInvestment(): array
    {
        $user = User::factory()->create(['country' => 'Sénégal']);

        $project = Project::create([
            'user_id'       => $user->id,
            'title'         => 'Projet test échéances',
            'slug'          => 'projet-test-echeances',
            'summary'       => 'Test',
            'description'   => 'Test',
            'country'       => 'Sénégal',
            'currency'      => 'EUR',
            'amount_needed' => 10000,
            'amount_raised' => 0,
            'stage'         => 'idea',
            'status'        => 'published',
        ]);

        $investment = Investment::create([
            'project_id'       => $project->id,
            'investor_id'      => $user->id,
            'amount'           => 152.45,     // net, devise projet
            'currency'         => 'EUR',
            'charged_amount'   => 106939.0,   // montant envoyé, devise du marché
            'charged_currency' => 'XOF',
            'type'             => 'equity',
            'status'           => 'pending',
        ]);

        return [$user, $investment];
    }
}
