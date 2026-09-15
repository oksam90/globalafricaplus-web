<?php

namespace Tests\Feature\Convention;

use App\Models\Investment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le porteur de projet est la SECONDE partie à la convention.
 *
 * Régression constatée en production le 15 septembre 2026 : l'investisseur
 * signait, puis la convention restait bloquée. Son lien de signature existait
 * bien en base, mais aucune réponse d'API ne le lui transmettait — la liste des
 * investissements était filtrée sur `investor_id`, le tableau de bord porteur
 * n'exposait rien, et les routes de convention renvoyaient 403. Le porteur n'a
 * pu signer qu'en allant chercher son lien en ligne de commande.
 */
class ProjectOwnerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_owner_can_read_the_investment_and_its_contract_routes(): void
    {
        [$owner, , $investment] = $this->makeInvestment();

        $this->actingAs($owner)
            ->getJson("/api/investments/{$investment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $investment->id);

        // Statut de signature : accessible aux deux parties.
        $this->actingAs($owner)
            ->getJson("/api/investments/{$investment->id}/contract/refresh")
            ->assertStatus(200);
    }

    public function test_a_stranger_is_still_refused(): void
    {
        [, , $investment] = $this->makeInvestment();
        $stranger = User::factory()->create([
            'kyc_level' => 'verified', 'kyc_verified_at' => now(), 'kyc_expires_at' => now()->addYear(),
        ]);

        $this->actingAs($stranger)
            ->getJson("/api/investments/{$investment->id}")
            ->assertForbidden();

        $this->actingAs($stranger)
            ->getJson("/api/investments/{$investment->id}/contract/signed")
            ->assertForbidden();

        $this->actingAs($stranger)
            ->postJson("/api/investments/{$investment->id}/contract/send")
            ->assertForbidden();
    }

    /** Le porteur doit trouver l'investissement dans SON tableau de bord. */
    public function test_dashboard_exposes_received_investments_to_the_owner(): void
    {
        [$owner, $investor, $investment] = $this->makeInvestment();

        $owner->forceFill(['active_role_slug' => 'entrepreneur'])->save();

        $response = $this->actingAs($owner)->getJson('/api/dashboard')->assertOk();
        $received = $response->json('role_data.received_investments');

        $this->assertIsArray($received, 'Le tableau de bord porteur doit lister les investissements reçus.');
        $this->assertCount(1, $received);
        $this->assertSame($investment->id, $received[0]['id']);
        $this->assertSame($investor->name, $received[0]['investor']['name']);
        $this->assertSame(1, $response->json('role_data.pending_signatures'));

        // Le lien de signature du porteur, et lui seul.
        $this->assertSame('https://sign.example.test/s/owner', $received[0]['my_sign_url']);
        $this->assertArrayNotHasKey('signature_sign_urls', $received[0]);

        // L'email de l'investisseur ne doit pas fuiter au porteur.
        $this->assertArrayNotHasKey('email', $received[0]['investor']);
    }

    /** L'investisseur ne doit jamais voir le lien de l'autre partie. */
    public function test_the_owner_link_is_never_exposed_to_the_investor(): void
    {
        [, $investor, $investment] = $this->makeInvestment();

        $payload = $this->actingAs($investor)
            ->getJson("/api/investments/{$investment->id}")
            ->assertOk()
            ->json('data');

        $this->assertSame('https://sign.example.test/s/investor', $payload['my_sign_url']);
        $this->assertArrayNotHasKey('signature_sign_urls', $payload);
    }

    /** @return array{0:User,1:User,2:Investment} owner, investor, investment */
    private function makeInvestment(): array
    {
        // Les routes de convention sont derrière `kyc.smile:verified` : les
        // DEUX parties doivent être vérifiées pour accéder à la signature.
        $kyc = ['kyc_level' => 'verified', 'kyc_verified_at' => now(), 'kyc_expires_at' => now()->addYear()];

        $owner    = User::factory()->create(['name' => 'Aminata Diop'] + $kyc);
        $investor = User::factory()->create(['name' => 'Ibrahim Sow'] + $kyc);

        $project = Project::create([
            'user_id'       => $owner->id,
            'title'         => 'Projet convention porteur',
            'slug'          => 'projet-convention-porteur',
            'summary'       => 'Test',
            'description'   => 'Test',
            'country'       => 'Sénégal',
            'currency'      => 'EUR',
            'amount_needed' => 5000,
            'amount_raised' => 500,
            'stage'         => 'idea',
            'status'        => 'published',
        ]);

        $investment = Investment::create([
            'project_id'           => $project->id,
            'investor_id'          => $investor->id,
            'amount'               => 500,
            'currency'             => 'EUR',
            'type'                 => 'reward',
            'status'               => 'escrow',
            'paid_at'              => now(),
            'contract_status'      => 'sent',
            'signature_provider'   => 'docuseal',
            'signature_request_id' => '8',
            'signature_sign_urls'  => [
                'investor' => 'https://sign.example.test/s/investor',
                'owner'    => 'https://sign.example.test/s/owner',
            ],
        ]);

        return [$owner, $investor, $investment];
    }
}
