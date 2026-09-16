<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tout webhook doit être exempté de CSRF — et le rester.
 *
 * Les routes de webhook vivent dans routes/web.php : elles héritent du groupe
 * `web`, protection CSRF comprise. Un émetteur externe n'a évidemment pas de
 * jeton de session, et reçoit donc 419 AVANT d'atteindre le contrôleur.
 *
 * L'oubli est invisible côté plateforme : aucune exception, aucune entrée de
 * journal — c'est l'émetteur qui constate l'échec. C'est précisément ce qui
 * s'est produit avec PawaPay et DocuSeal, intégrés sans que la liste
 * d'exclusion de bootstrap/app.php soit mise à jour. Les chemins de repli
 * (vérification au retour du paiement, bouton « Rafraîchir ») masquaient la
 * panne.
 *
 * Ce test échouera à la prochaine route de webhook ajoutée sans exclusion.
 */
class WebhookCsrfExemptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La protection CSRF est désactivée par défaut dans les tests : on la
        // réactive, sinon ce test ne prouverait rien.
        $this->withMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_every_webhook_route_is_exempt_from_csrf(): void
    {
        $webhooks = $this->webhookRoutes();

        $this->assertNotEmpty($webhooks, 'Aucune route de webhook trouvée — le test ne prouverait rien.');

        $rejected = [];
        foreach ($webhooks as $uri) {
            $status = $this->postJson('/' . $uri, [])->getStatusCode();

            if ($status === 419) {
                $rejected[] = $uri;
            }
        }

        $this->assertSame([], $rejected, sprintf(
            "Webhook(s) rejeté(s) en 419 par la protection CSRF : %s\n"
            . "Ajoutez-les à validateCsrfTokens(except: [...]) dans bootstrap/app.php.",
            implode(', ', $rejected),
        ));
    }

    /**
     * Les webhooks connus doivent être servis par l'application, pas par le
     * routeur : un 404 signalerait une route disparue ou renommée.
     */
    public function test_the_expected_webhooks_are_registered(): void
    {
        $registered = $this->webhookRoutes();

        foreach (['pawapay', 'docuseal', 'paydunya'] as $expected) {
            $found = array_filter($registered, fn ($uri) => str_contains($uri, $expected));

            $this->assertNotEmpty($found, "Aucune route de webhook pour « {$expected} ».");
        }
    }

    /** @return list<string> URIs POST contenant « webhooks » */
    private function webhookRoutes(): array
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            if (!in_array('POST', $route->methods(), true)) {
                continue;
            }
            if (!str_contains($route->uri(), 'webhooks')) {
                continue;
            }

            // Les paramètres de route sont remplacés par une valeur plausible.
            $uris[] = preg_replace('/\{[^}]+\}/', 'deposits', $route->uri());
        }

        return array_values(array_unique($uris));
    }
}
