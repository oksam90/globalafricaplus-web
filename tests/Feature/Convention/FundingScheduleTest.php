<?php

namespace Tests\Feature\Convention;

use App\Models\Investment;
use App\Models\Project;
use App\Models\User;
use App\Services\Convention\ConventionContext;
use App\Services\Convention\ConventionGenerator;
use App\Services\Payment\InstallmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * La convention doit décrire l'échéancier RÉELLEMENT souscrit.
 *
 * Le popup « Investir dans ce projet » autorise 2 à 12 échéances (hebdomadaire,
 * bimensuelle, mensuelle). Avant cette correction, la convention affirmait un
 * versement unique « au plus tard le [DATE] » et promettait 40 % du Montant au
 * premier Jalon un mois après la souscription — alors qu'en douze échéances
 * mensuelles, moins d'un douzième est encaissé à cette date.
 */
class FundingScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pawapay.api_token'   => 'test-token',
            'paydunya.master_key' => 'test-key',
            // La conversion PDF passe par LibreOffice : hors sujet ici.
            'conventions.pdf.enabled' => false,
        ]);
    }

    public function test_single_payment_yields_one_funding_row(): void
    {
        [, $investment] = $this->makeInvestment();

        $ctx = app(ConventionContext::class)->build($investment);

        $this->assertCount(1, $ctx['funding_schedule']);
        $this->assertSame('en un versement unique', $ctx['data']['funding_mode']);
        $this->assertSame('106 939 XOF', $ctx['funding_schedule'][0]['cumulative']);
    }

    public function test_installment_plan_drives_the_funding_schedule(): void
    {
        [$user, $investment] = $this->makeInvestment();

        app(InstallmentService::class)->createPlan(
            user: $user,
            payable: $investment,
            totalAmount: (float) $investment->charged_amount,
            currency: $investment->charged_currency,
            totalInstallments: 12,
            frequency: 'monthly',
            startsAt: Carbon::parse('2026-01-15'),
            paymentMethod: 'card',
        );

        $ctx = app(ConventionContext::class)->build($investment->fresh());
        $rows = $ctx['funding_schedule'];

        $this->assertCount(12, $rows);
        $this->assertSame('15/01/2026', $rows[0]['date']);
        $this->assertSame('15/12/2026', $rows[11]['date']);
        $this->assertSame('Carte bancaire', $rows[0]['method']);

        // Le cumul de la dernière ligne doit égaler le total appelé.
        $this->assertSame($ctx['data']['funding_total'], $rows[11]['cumulative']);

        $this->assertStringContainsString('12 échéances mensuelles', $ctx['data']['funding_mode']);

        // « Mis à disposition au plus tard le » = dernière échéance, et non la
        // date du jour : le Séquestre n'est plein qu'à ce moment-là.
        $this->assertSame('15/12/2026', $ctx['data']['disposition_date']);
    }

    /**
     * Cœur de la régression : aucun Jalon ne peut être daté avant que le cumul
     * versé n'atteigne sa Tranche (Article 6.3).
     */
    public function test_milestone_dates_never_precede_the_funding_they_require(): void
    {
        Carbon::setTestNow('2026-03-10 09:00:00');

        [$user, $investment] = $this->makeInvestment();

        app(InstallmentService::class)->createPlan(
            user: $user,
            payable: $investment,
            totalAmount: (float) $investment->charged_amount,
            currency: $investment->charged_currency,
            totalInstallments: 12,
            frequency: 'monthly',
        );

        $ctx = app(ConventionContext::class)->build($investment->fresh());

        // Jalons par défaut : 40 % à M+1, 40 % à M+3, 20 % à M+6.
        // En 12 échéances mensuelles, le cumul versé n'atteint 40 % qu'à la
        // 5e échéance (41,7 %), 80 % qu'à la 10e (83,3 %) et 100 % qu'à la 12e.
        // Chaque Jalon est donc reporté à la date de financement suffisante.
        $this->assertSame('10/07/2026', $ctx['milestones'][0]['date']); // M+4, pas M+1
        $this->assertSame('10/12/2026', $ctx['milestones'][1]['date']); // M+9, pas M+3
        $this->assertSame('10/02/2027', $ctx['milestones'][2]['date']); // M+11, pas M+6

        Carbon::setTestNow();
    }

    /** Sans fractionnement, les échéances des Jalons ne bougent pas. */
    public function test_single_payment_leaves_milestone_dates_untouched(): void
    {
        Carbon::setTestNow('2026-03-10 09:00:00');

        [, $investment] = $this->makeInvestment();

        $ctx = app(ConventionContext::class)->build($investment);

        $this->assertSame('10/04/2026', $ctx['milestones'][0]['date']);
        $this->assertSame('10/06/2026', $ctx['milestones'][1]['date']);
        $this->assertSame('10/09/2026', $ctx['milestones'][2]['date']);

        Carbon::setTestNow();
    }

    /** Le tableau du gabarit ne compte qu'une ligne modèle : elle doit être clonée. */
    public function test_generated_docx_repeats_one_row_per_installment(): void
    {
        Storage::fake('local');
        config(['conventions.disk' => 'local']);

        [$user, $investment] = $this->makeInvestment();

        app(InstallmentService::class)->createPlan(
            user: $user,
            payable: $investment,
            totalAmount: (float) $investment->charged_amount,
            currency: $investment->charged_currency,
            totalInstallments: 4,
            frequency: 'weekly',
            startsAt: Carbon::parse('2026-03-02'),
        );

        $path = app(ConventionGenerator::class)->generateForInvestment($investment->fresh());
        $xml  = $this->documentXml(Storage::disk('local')->path($path));

        // Les placeholders de la ligne modèle ont tous disparu…
        foreach (['[N° ÉCH.]', '[DATE ÉCH.]', '[MONTANT ÉCH.]', '[CUMUL ÉCH.]', '[MOYEN ÉCH.]'] as $marker) {
            $this->assertStringNotContainsString($marker, $xml, "Placeholder non substitué : {$marker}");
        }

        // …et les 4 échéances hebdomadaires figurent bien dans le document.
        foreach (['02/03/2026', '09/03/2026', '16/03/2026', '23/03/2026'] as $date) {
            $this->assertStringContainsString($date, $xml, "Échéance manquante : {$date}");
        }

        $this->assertStringContainsString('en 4 échéances hebdomadaires', $xml);
        $this->assertStringNotContainsString('[MODALITÉ DE VERSEMENT]', $xml);
        $this->assertStringNotContainsString('[TOTAL VERSEMENTS]', $xml);
    }

    /**
     * Les nouveaux placeholders ne doivent pas décaler les files d'injection
     * existantes : les clauses réservées au conseil juridique restent en place.
     */
    public function test_legal_placeholders_are_left_untouched(): void
    {
        Storage::fake('local');
        config(['conventions.disk' => 'local']);

        [, $investment] = $this->makeInvestment(['type' => 'loan']);

        $path = app(ConventionGenerator::class)->generateForInvestment($investment);
        $xml  = $this->documentXml(Storage::disk('local')->path($path));

        foreach (['[TAUX]', '[DURÉE]', '[CAUTION / GARANTIE / NÉANT]', '[JURIDICTION / ARBITRAGE]'] as $kept) {
            $this->assertStringContainsString($kept, $xml, "Clause juridique perdue : {$kept}");
        }
    }

    private function documentXml(string $docx): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($docx) === true, "Archive illisible : {$docx}");
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return html_entity_decode((string) $xml, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** @return array{0:User,1:Investment} */
    private function makeInvestment(array $overrides = []): array
    {
        $user = User::factory()->create(['country' => 'Sénégal', 'city' => 'Dakar']);

        $project = Project::create([
            'user_id'       => $user->id,
            'title'         => 'Projet convention échéances',
            'slug'          => 'projet-convention-echeances',
            'summary'       => 'Test',
            'description'   => 'Test',
            'country'       => 'Sénégal',
            'city'          => 'Dakar',
            'currency'      => 'EUR',
            'amount_needed' => 10000,
            'amount_raised' => 0,
            'stage'         => 'idea',
            'status'        => 'published',
        ]);

        $investment = Investment::create(array_merge([
            'project_id'       => $project->id,
            'investor_id'      => $user->id,
            'amount'           => 152.45,
            'currency'         => 'EUR',
            'charged_amount'   => 106939.0,
            'charged_currency' => 'XOF',
            'type'             => 'equity',
            'status'           => 'pending',
        ], $overrides));

        return [$user, $investment];
    }
}
