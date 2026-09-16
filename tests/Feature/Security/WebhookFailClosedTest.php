<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Un secret de webhook absent doit FERMER la porte, pas l'ouvrir.
 *
 * Constat F6 de l'audit du 16/09/2026 : trois gestionnaires suivaient le motif
 * « si le secret est vide, on laisse passer ». Une variable d'environnement
 * oubliée désactivait donc l'authentification sans le moindre signal — et
 * c'était le cas en production sur la route Yousign, prestataire pourtant
 * remplacé par DocuSeal.
 *
 * Le modèle correct existait déjà dans le projet : VerifyPayDunyaWebhook
 * calcule un hash nul quand la clé manque, la comparaison échoue, 401.
 */
class WebhookFailClosedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Aucun appel sortant ne doit partir d'un webhook refusé.
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 200)]);
    }

    public function test_docuseal_rejects_when_no_secret_is_configured(): void
    {
        config(['docuseal.webhook_secret' => '']);

        $this->postJson('/api/v1/webhooks/docuseal', [
            'event_type' => 'form.completed',
            'data'       => ['submission_id' => 1],
        ])->assertOk()->assertJson(['received' => false]);

        Http::assertNothingSent();
    }

    public function test_yousign_rejects_when_no_secret_is_configured(): void
    {
        config(['yousign.webhook_secret' => '']);

        $this->postJson('/api/v1/webhooks/yousign', [
            'data' => ['signature_request' => ['id' => 'abc-123']],
        ])->assertOk()->assertJson(['received' => false]);

        Http::assertNothingSent();
    }

    /** Avec un secret, une signature valide doit toujours passer. */
    public function test_docuseal_still_accepts_a_correctly_signed_call(): void
    {
        $secret = 'whsec_test_failclosed';
        config([
            'docuseal.webhook_secret' => $secret,
            'docuseal.base_url'       => 'https://sign.example.test',
            'docuseal.api_token'      => 'token-test',
        ]);

        $payload   = ['event_type' => 'form.completed', 'data' => ['submission_id' => 1]];
        $timestamp = (string) time();
        $body      = json_encode($payload);

        $this->postJson('/api/v1/webhooks/docuseal', $payload, [
            'X-Docuseal-Signature' => $timestamp . '.'
                . hash_hmac('sha256', $timestamp . '.' . $body, $secret),
        ])->assertOk()->assertJson(['received' => true]);
    }

    /** Avec un secret mais sans signature, le refus reste ferme. */
    public function test_docuseal_rejects_an_unsigned_call_when_a_secret_exists(): void
    {
        config(['docuseal.webhook_secret' => 'whsec_test_failclosed']);

        $this->postJson('/api/v1/webhooks/docuseal', ['event_type' => 'form.completed'])
            ->assertOk()
            ->assertJson(['received' => false]);

        Http::assertNothingSent();
    }
}
