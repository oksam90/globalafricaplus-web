<?php

namespace Tests\Feature\Convention;

use App\Models\Investment;
use App\Models\Project;
use App\Models\User;
use App\Services\Convention\ConventionSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Mise à la signature via DocuSeal, en mode « gabarit ».
 *
 * Trois régressions couvertes ici :
 *   1. les rôles envoyés doivent être ceux du TYPE de convention
 *      (Prêteur/Emprunteur, Donateur/Bénéficiaire…), sans quoi les champs du
 *      gabarit ne sont rattachés à personne ;
 *   2. les liens de signature doivent être indexés sur une clé interne stable,
 *      le libellé du rôle variant d'un type à l'autre ;
 *   3. l'envoi ne doit pas dépendre du PDF : en mode gabarit, le document vit
 *      chez DocuSeal et LibreOffice peut être absent du serveur.
 */
class SignatureProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config([
            'signature.provider'      => 'docuseal',
            'docuseal.base_url'       => 'https://sign.example.test',
            'docuseal.api_token'      => 'token-test',
            'docuseal.mode'           => 'template',
            'docuseal.send_email'     => false,
            'docuseal.templates'      => ['equity' => 5, 'donation' => 6, 'loan' => 7, 'reward' => 4],
            'conventions.disk'        => 'local',
            'conventions.pdf.enabled' => false, // LibreOffice absent, comme sur le VPS
        ]);
    }

    /**
     * @return array<string,array{0:string,1:string,2:string}>  type, rôle 1, rôle 2
     */
    public static function conventionTypes(): array
    {
        return [
            'prêt'          => ['loan', 'Prêteur', 'Emprunteur'],
            'don'           => ['donation', 'Donateur', 'Bénéficiaire'],
            'participation' => ['equity', 'Investisseur', 'Porteur'],
            'contrepartie'  => ['reward', 'Contributeur', 'Porteur'],
        ];
    }

    #[DataProvider('conventionTypes')]
    public function test_roles_match_the_convention_type(string $type, string $first, string $second): void
    {
        $this->fakeSubmission($first, $second);

        $investment = $this->makeInvestment($type);
        app(ConventionSignatureService::class)->sendForSignature($investment);

        Http::assertSent(function ($request) use ($type, $first, $second) {
            $body = $request->data();

            return str_contains($request->url(), '/submissions')
                && (int) $body['template_id'] === (int) config("docuseal.templates.{$type}")
                && $body['submitters'][0]['role'] === $first
                && $body['submitters'][1]['role'] === $second;
        });
    }

    public function test_sign_urls_are_indexed_on_internal_keys(): void
    {
        $this->fakeSubmission('Prêteur', 'Emprunteur');

        $investment = $this->makeInvestment('loan');
        app(ConventionSignatureService::class)->sendForSignature($investment);

        $urls = $investment->fresh()->signature_sign_urls;

        // Surtout pas indexés sur « Prêteur » / « Emprunteur » : le modèle ne
        // connaît pas ces libellés, qui changent d'un type à l'autre.
        $this->assertSame(['investor', 'owner'], array_keys($urls));
        $this->assertSame('https://sign.example.test/s/aaa', $urls['investor']);
        $this->assertSame('https://sign.example.test/s/bbb', $urls['owner']);
    }

    /** Chacun ne voit que SON lien — jamais celui de l'autre partie. */
    public function test_each_party_only_sees_its_own_sign_url(): void
    {
        $this->fakeSubmission('Prêteur', 'Emprunteur');

        $investment = $this->makeInvestment('loan');
        app(ConventionSignatureService::class)->sendForSignature($investment);
        $investment = $investment->fresh();

        $this->actingAs($investment->investor);
        $this->assertSame('https://sign.example.test/s/aaa', $investment->fresh()->my_sign_url);

        $this->actingAs($investment->project->user);
        $this->assertSame('https://sign.example.test/s/bbb', $investment->fresh()->my_sign_url);

        $this->actingAs(User::factory()->create());
        $this->assertNull($investment->fresh()->my_sign_url);
    }

    /**
     * Régression : sans LibreOffice, `contract_pdf_path` reste vide. En mode
     * « gabarit » cela ne doit rien empêcher — le PDF n'est qu'une archive.
     */
    public function test_sending_works_without_the_pdf_in_template_mode(): void
    {
        $this->fakeSubmission('Investisseur', 'Porteur');

        $investment = $this->makeInvestment('equity');
        app(ConventionSignatureService::class)->sendForSignature($investment);

        $investment = $investment->fresh();

        $this->assertNull($investment->contract_pdf_path, 'Aucun PDF attendu sans LibreOffice.');
        $this->assertNotNull($investment->contract_path, 'Le .docx doit malgré tout être archivé.');
        $this->assertSame('sent', $investment->contract_status);
        $this->assertSame('88', $investment->signature_request_id);
    }

    /** Le mode « one-off » téléverse le PDF : là, son absence est bloquante. */
    public function test_oneoff_mode_still_requires_the_pdf(): void
    {
        config(['docuseal.mode' => 'oneoff']);
        $this->fakeSubmission('Investisseur', 'Porteur');

        $this->expectExceptionMessage('PDF de la convention introuvable');

        app(ConventionSignatureService::class)->sendForSignature($this->makeInvestment('equity'));
    }

    /** Un envoi déjà effectué ne doit jamais être rejoué. */
    public function test_sending_is_idempotent(): void
    {
        $this->fakeSubmission('Prêteur', 'Emprunteur');

        $investment = $this->makeInvestment('loan');
        app(ConventionSignatureService::class)->sendForSignature($investment);
        app(ConventionSignatureService::class)->sendForSignature($investment->fresh());

        Http::assertSentCount(1);
    }

    // ─────────────────────────── helpers ───────────────────────────

    private function fakeSubmission(string $firstRole, string $secondRole): void
    {
        Http::fake([
            '*/api/submissions' => Http::response([
                ['id' => 101, 'submission_id' => 88, 'role' => $firstRole,
                 'embed_src' => 'https://sign.example.test/s/aaa'],
                ['id' => 102, 'submission_id' => 88, 'role' => $secondRole,
                 'embed_src' => 'https://sign.example.test/s/bbb'],
            ], 200),
        ]);
    }

    private function makeInvestment(string $type): Investment
    {
        $investor = User::factory()->create(['name' => 'Ibrahim Sow']);
        $owner    = User::factory()->create(['name' => 'Aminata Diop']);

        $project = Project::create([
            'user_id'       => $owner->id,
            'title'         => 'Projet signature ' . $type,
            'slug'          => 'projet-signature-' . $type,
            'summary'       => 'Test',
            'description'   => 'Test',
            'country'       => 'Sénégal',
            'currency'      => 'EUR',
            'amount_needed' => 5000,
            'amount_raised' => 0,
            'stage'         => 'idea',
            'status'        => 'published',
        ]);

        return Investment::create([
            'project_id'  => $project->id,
            'investor_id' => $investor->id,
            'amount'      => 500,
            'currency'    => 'EUR',
            'type'        => $type,
            'status'      => 'escrow',
            'paid_at'     => now(),
        ]);
    }
}
