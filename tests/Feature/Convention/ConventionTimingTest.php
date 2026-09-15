<?php

namespace Tests\Feature\Convention;

use App\Jobs\PrepareConvention;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Investment;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payment\InstallmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Deux actes, deux moments.
 *
 *   PRODUIRE  — dès la validation, au clic sur « Payer ». Aucun effet vers
 *               l'extérieur : l'investisseur peut lire son contrat pendant
 *               qu'il règle.
 *   ENVOYER   — au premier encaissement confirmé. Envoyer sollicite le porteur
 *               de projet ; cela ne doit pas arriver pour un paiement
 *               abandonné au checkout, fréquent en mobile money.
 *
 * En paiement fractionné, « premier encaissement » est la PREMIÈRE échéance et
 * non la dernière : un plan sur douze mois laissait auparavant l'investisseur
 * engagé et payant pendant onze mois sans convention signée.
 */
class ConventionTimingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Abonnement, KYC et criblage AML ont leurs propres tests : ici on ne
        // vérifie que le MOMENT de production et d'envoi de la convention.
        $this->withoutMiddleware([
            \App\Http\Middleware\CheckSubscription::class,
            \App\Http\Middleware\RequireKYCLevel::class,
            \App\Http\Middleware\RequireAmlCleared::class,
        ]);

        Http::fake([
            '*/v2/paymentpage' => Http::response([
                'redirectUrl' => 'https://pay.example.test/checkout/abc',
                'status'      => 'ACCEPTED',
            ], 200),
            '*' => Http::response([], 200),
        ]);

        config([
            'signature.provider'    => 'docuseal',
            'signature.generate_on' => 'validation',
            'signature.send_on'     => 'first_payment',
            'signature.auto_send'   => true,
            'docuseal.api_token'    => 'token-test',
            'paydunya.master_key'   => 'test-key',
            'pawapay.api_token'     => 'test-token',
        ]);
    }

    /** À la validation : on produit, mais on n'envoie pas. */
    public function test_validating_generates_the_convention_without_sending_it(): void
    {
        Queue::fake();

        [$user, $project] = $this->makeProject();

        $this->actingAs($user)
            ->postJson('/api/investments', [
                'project_id' => $project->id,
                'net_amount' => 50,
                'type'       => 'reward',
            ])
            ->assertCreated();

        $investment = Investment::latest()->first();

        Queue::assertPushed(PrepareConvention::class, function ($job) use ($investment) {
            return $job->investmentId === $investment->id && $job->send === false;
        });
    }

    /** En fractionné, le plan doit exister avant que la convention soit produite. */
    public function test_the_installment_plan_exists_before_the_convention_is_queued(): void
    {
        $planExisted = null;
        Queue::fake();

        [$user, $project] = $this->makeProject();

        $this->actingAs($user)
            ->postJson('/api/investments', [
                'project_id'   => $project->id,
                'net_amount'   => 100,
                'type'         => 'loan',
                'installments' => 4,
                'frequency'    => 'monthly',
            ])
            ->assertCreated();

        $investment = Investment::latest()->first();

        Queue::assertPushed(PrepareConvention::class, function ($job) use ($investment, &$planExisted) {
            $planExisted = InstallmentPlan::where('payable_type', Investment::class)
                ->where('payable_id', $investment->id)
                ->exists();

            return $job->investmentId === $investment->id && $job->send === false;
        });

        $this->assertTrue($planExisted, 'Le plan d\'échéances doit précéder la convention.');
    }

    /**
     * Cœur de la correction : la PREMIÈRE échéance déclenche l'envoi, pas la
     * dernière.
     */
    public function test_the_first_paid_installment_sends_the_convention(): void
    {
        [$user, $project] = $this->makeProject();
        [$investment, $plan] = $this->makePlan($user, $project, 4);

        Queue::fake();

        $first = $plan->installments()->orderBy('number')->first();
        app(InstallmentService::class)->markPaid($first, $this->makeTransaction($user));

        Queue::assertPushed(PrepareConvention::class, function ($job) use ($investment) {
            return $job->investmentId === $investment->id && $job->send === true;
        });
    }

    /** Les échéances suivantes ne relancent rien. */
    public function test_later_installments_do_not_send_again(): void
    {
        [$user, $project] = $this->makeProject();
        [, $plan] = $this->makePlan($user, $project, 4);

        $service = app(InstallmentService::class);
        $service->markPaid($plan->installments()->orderBy('number')->first(), $this->makeTransaction($user));

        Queue::fake(); // on n'observe que ce qui suit la 1re échéance

        $second = $plan->fresh()->installments()->where('number', 2)->first();
        $service->markPaid($second, $this->makeTransaction($user));

        Queue::assertNotPushed(PrepareConvention::class);
    }

    /** Une demande déjà partie ne doit jamais être rejouée. */
    public function test_nothing_is_sent_when_a_request_already_exists(): void
    {
        [$user, $project] = $this->makeProject();
        [$investment, $plan] = $this->makePlan($user, $project, 3);

        $investment->forceFill([
            'signature_provider'   => 'docuseal',
            'signature_request_id' => '42',
            'contract_status'      => 'sent',
        ])->save();

        Queue::fake();

        app(InstallmentService::class)->markPaid(
            $plan->installments()->orderBy('number')->first(),
            $this->makeTransaction($user),
        );

        Queue::assertNotPushed(PrepareConvention::class);
    }

    /** Le job ne fait que produire quand `send` est faux. */
    public function test_the_job_generates_without_sending(): void
    {
        [$user, $project] = $this->makeProject();

        $investment = Investment::create([
            'project_id'  => $project->id,
            'investor_id' => $user->id,
            'amount'      => 100,
            'currency'    => 'EUR',
            'type'        => 'reward',
            'status'      => 'pending',
        ]);

        (new PrepareConvention($investment->id, send: false))->handle(
            app(\App\Services\Convention\ConventionGenerator::class),
            app(\App\Services\Convention\ConventionSignatureService::class),
        );

        $investment->refresh();

        $this->assertSame('generated', $investment->contract_status);
        $this->assertNotNull($investment->contract_path);
        $this->assertNull($investment->signature_request_id, 'Rien ne doit partir à la signature.');
    }

    /** L'investisseur peut lire sa convention avant d'avoir payé. */
    public function test_the_convention_is_readable_while_the_payment_is_pending(): void
    {
        [$user, $project] = $this->makeProject();

        $investment = Investment::create([
            'project_id'  => $project->id,
            'investor_id' => $user->id,
            'amount'      => 100,
            'currency'    => 'EUR',
            'type'        => 'reward',
            'status'      => 'pending',
        ]);

        $this->actingAs($user)
            ->get("/api/investments/{$investment->id}/contract")
            ->assertOk();
    }

    // ─────────────────────────── helpers ───────────────────────────

    /** @return array{0:Investment,1:InstallmentPlan} */
    private function makePlan(User $user, Project $project, int $count): array
    {
        $investment = Investment::create([
            'project_id'       => $project->id,
            'investor_id'      => $user->id,
            'amount'           => 152.45,
            'currency'         => 'EUR',
            'charged_amount'   => 106939.0,
            'charged_currency' => 'XOF',
            'type'             => 'loan',
            'status'           => 'pending',
        ]);

        $plan = app(InstallmentService::class)->createPlan(
            user: $user,
            payable: $investment,
            totalAmount: (float) $investment->charged_amount,
            currency: $investment->charged_currency,
            totalInstallments: $count,
            frequency: 'monthly',
        );

        return [$investment, $plan];
    }

    private function makeTransaction(User $user): Transaction
    {
        return Transaction::create([
            'user_id'           => $user->id,
            'amount'            => 26735,
            'currency'          => 'XOF',
            'gateway'           => 'pawapay',
            'gateway_reference' => 'tx_' . uniqid(),
            'payment_type'      => 'investment',
            'status'            => 'completed',
            'customer_name'     => $user->name,
            'customer_email'    => $user->email,
            'customer_country'  => 'SN',
        ]);
    }

    /** @return array{0:User,1:Project} */
    private function makeProject(): array
    {
        $user = User::factory()->create([
            'country'         => 'Sénégal',
            'kyc_level'       => 'verified',
            'kyc_verified_at' => now(),
            'kyc_expires_at'  => now()->addYear(),
        ]);

        $owner = User::factory()->create(['country' => 'Sénégal']);

        $project = Project::create([
            'user_id'       => $owner->id,
            'title'         => 'Projet moment convention',
            'slug'          => 'projet-moment-convention',
            'summary'       => 'Test',
            'description'   => 'Test',
            'country'       => 'Sénégal',
            'currency'      => 'EUR',
            'amount_needed' => 10000,
            'amount_raised' => 0,
            'stage'         => 'idea',
            'status'        => 'published',
        ]);

        return [$user, $project];
    }
}
