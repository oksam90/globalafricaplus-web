<?php

namespace Tests\Feature\Convention;

use App\Models\Investment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Webhook DocuSeal — vérification de la signature HMAC.
 *
 * DocuSeal signe « {timestamp}.{corps brut} » en HMAC-SHA256 avec le secret
 * COMPLET, préfixe `whsec_` inclus, et tolère 5 minutes de décalage d'horloge
 * (cf. lib/webhook_urls/signatures.rb du dépôt DocuSeal). Une erreur sur l'un
 * de ces trois points ferait silencieusement tomber tous les événements.
 */
class DocuSealWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_0123456789abcdef';

    private const URL = '/api/v1/webhooks/docuseal';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'docuseal.webhook_secret' => self::SECRET,
            'docuseal.base_url'       => 'https://sign.example.test',
            'docuseal.api_token'      => 'token-test',
            'signature.provider'      => 'docuseal',
        ]);

        // Le webhook n'est qu'un déclencheur : le statut est re-lu via l'API.
        Http::fake([
            '*/api/submissions/*' => Http::response(['id' => 77, 'status' => 'pending'], 200),
        ]);
    }

    public function test_valid_signature_is_accepted_and_triggers_a_sync(): void
    {
        $investment = $this->makeSentInvestment();

        $this->postJson(self::URL, $this->payload(), $this->signed($this->payload()))
            ->assertOk()
            ->assertJson(['received' => true]);

        // Statut re-vérifié auprès de DocuSeal, donc appel sortant effectué.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/submissions/'));

        $this->assertSame('sent', $investment->fresh()->contract_status);
    }

    public function test_missing_signature_is_rejected_without_calling_the_api(): void
    {
        $this->makeSentInvestment();

        $this->postJson(self::URL, $this->payload())
            ->assertOk()
            ->assertJson(['received' => false]);

        Http::assertNothingSent();
    }

    public function test_signature_computed_without_the_whsec_prefix_is_rejected(): void
    {
        $this->makeSentInvestment();

        $body      = json_encode($this->payload());
        $timestamp = (string) time();
        $truncated = hash_hmac('sha256', $timestamp . '.' . $body, substr(self::SECRET, strlen('whsec_')));

        $this->call('POST', self::URL, [], [], [], [
            'CONTENT_TYPE'              => 'application/json',
            'HTTP_ACCEPT'               => 'application/json',
            'HTTP_X_DOCUSEAL_SIGNATURE' => $timestamp . '.' . $truncated,
        ], $body)->assertOk()->assertJson(['received' => false]);

        Http::assertNothingSent();
    }

    public function test_stale_timestamp_is_rejected_as_replay(): void
    {
        $this->makeSentInvestment();

        $headers = $this->signed($this->payload(), time() - 600);

        $this->postJson(self::URL, $this->payload(), $headers)
            ->assertOk()
            ->assertJson(['received' => false]);

        Http::assertNothingSent();
    }

    public function test_tampered_body_is_rejected(): void
    {
        $this->makeSentInvestment();

        // Signature calculée sur le corps d'origine, corps émis modifié.
        $headers = $this->signed($this->payload());

        $this->postJson(self::URL, $this->payload(['event_type' => 'form.declined']), $headers)
            ->assertOk()
            ->assertJson(['received' => false]);

        Http::assertNothingSent();
    }

    /** Un événement pour une soumission inconnue ne doit pas provoquer d'erreur. */
    public function test_unknown_submission_is_acknowledged_without_sync(): void
    {
        $payload = $this->payload(['data' => ['submission_id' => 999999]]);

        $this->postJson(self::URL, $payload, $this->signed($payload))
            ->assertOk()
            ->assertJson(['received' => true]);

        Http::assertNothingSent();
    }

    // ─────────────────────────── helpers ───────────────────────────

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'event_type' => 'form.completed',
            'timestamp'  => now()->toIso8601String(),
            'data'       => [
                'submission_id' => 77,
                'external_id'   => 'investment-1',
                'role'          => 'Prêteur',
            ],
        ], $overrides);
    }

    /** @return array<string,string> */
    private function signed(array $payload, ?int $timestamp = null): array
    {
        $timestamp ??= time();
        $body = json_encode($payload);

        return [
            'X-Docuseal-Signature' => $timestamp . '.'
                . hash_hmac('sha256', $timestamp . '.' . $body, self::SECRET),
        ];
    }

    private function makeSentInvestment(): Investment
    {
        $user = User::factory()->create();

        $project = Project::create([
            'user_id'       => $user->id,
            'title'         => 'Projet webhook',
            'slug'          => 'projet-webhook',
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
            'project_id'           => $project->id,
            'investor_id'          => $user->id,
            'amount'               => 100,
            'currency'             => 'EUR',
            'type'                 => 'loan',
            'status'               => 'escrow',
            'contract_status'      => 'sent',
            'signature_provider'   => 'docuseal',
            'signature_request_id' => '77',
        ]);
    }
}
