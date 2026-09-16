<?php

namespace Tests\Feature\Security;

use App\Exceptions\GatewayException;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Constats F7 et F8 de l'audit du 16/09/2026.
 *
 * Le motif d'origine — `catch (\Throwable $e)` suivi de `$e->getMessage()`
 * renvoyé en 422 — confondait trois choses :
 *
 *   • un refus d'autorisation, qui répondait 422 en confirmant au passage
 *     l'existence de la ressource ;
 *   • une règle métier, dont le message est bien destiné à l'utilisateur ;
 *   • une erreur interne, dont le message est écrit pour un développeur.
 *
 * Le dernier cas n'était pas théorique : un jeton PSP invalide faisait remonter
 * « The API token in the request is invalid » jusqu'au navigateur.
 */
class ErrorDisclosureTest extends TestCase
{
    use RefreshDatabase;

    private function triage(\Throwable $e, string $fallback = 'Une erreur est survenue.'): JsonResponse
    {
        $controller = new class extends Controller {
            public function call(\Throwable $e, string $fallback): JsonResponse
            {
                return $this->failure($e, $fallback);
            }
        };

        return $controller->call($e, $fallback);
    }

    public function test_an_authorization_failure_answers_403_without_naming_the_resource(): void
    {
        $response = $this->triage(
            new AuthorizationException("Vous n'êtes pas l'investisseur de ce jalon.")
        );

        $this->assertSame(403, $response->getStatusCode());

        $body = $response->getData(true);
        $this->assertSame('Accès refusé.', $body['message']);
        $this->assertStringNotContainsString('jalon', $body['message']);
    }

    public function test_a_business_rule_still_reaches_the_user(): void
    {
        $response = $this->triage(
            new RuntimeException('La fenêtre de garantie de 30 jours est dépassée.')
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            'La fenêtre de garantie de 30 jours est dépassée.',
            $response->getData(true)['message'],
        );
    }

    /** Le message d'un prestataire décrit NOTRE configuration : il ne sort pas. */
    public function test_a_gateway_failure_is_not_disclosed(): void
    {
        $leak = 'PawaPay POST /v2/paymentpage — HTTP 401 : The API token in the request is invalid.';

        $response = $this->triage(new GatewayException($leak));

        $this->assertSame(502, $response->getStatusCode());

        $body = $response->getData(true);
        $this->assertStringNotContainsString('API token', $body['message']);
        $this->assertStringNotContainsString('PawaPay', $body['message']);
        $this->assertStringNotContainsString('401', $body['message']);
    }

    /** Une erreur interne ne dit rien — ni table, ni chemin, ni type. */
    public function test_an_internal_error_is_not_disclosed(): void
    {
        $leak = "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'investments.secret_col'";

        $response = $this->triage(new \ErrorException($leak), 'Impossible de traiter la demande.');

        $this->assertSame(500, $response->getStatusCode());

        $body = $response->getData(true);
        $this->assertSame('Impossible de traiter la demande.', $body['message']);
        $this->assertStringNotContainsString('SQLSTATE', $body['message']);
        $this->assertStringNotContainsString('investments', $body['message']);
    }

    /**
     * Garde-fou structurel : aucun contrôleur ne doit renvoyer un message
     * d'exception brut. Ce test échouera si le motif réapparaît.
     */
    public function test_no_controller_returns_a_raw_exception_message(): void
    {
        $offenders = [];

        foreach (glob(app_path('Http/Controllers/**/*.php')) as $file) {
            foreach (file($file) as $n => $line) {
                if (!str_contains($line, '$e->getMessage()')) {
                    continue;
                }
                // Les journaux ont le droit — c'est leur rôle.
                if (str_contains($line, 'Log::') || str_contains($line, "'message' => \$e->getMessage()") === false) {
                    continue;
                }
                $offenders[] = basename($file) . ':' . ($n + 1);
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "Message d'exception renvoyé tel quel : %s\nUtilisez \$this->failure(\$e) — cf. Controller::failure().",
            implode(', ', $offenders),
        ));
    }
}
