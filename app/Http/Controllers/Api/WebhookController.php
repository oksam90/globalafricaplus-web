<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPawaPayCallback;
use App\Jobs\ProcessPayDunyaWebhook;
use App\Models\Investment;
use App\Services\Convention\ConventionSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives IPN webhooks from payment gateways.
 *
 * Signature verification is done by the `paydunya.webhook` middleware
 * BEFORE this controller runs. Here we only enqueue async processing and
 * return 200 as fast as possible (PayDunya retries on any non-2xx).
 */
class WebhookController extends Controller
{
    /** Tolérance d'horloge des signatures DocuSeal, en secondes (5 min). */
    private const DOCUSEAL_SIGNATURE_TOLERANCE = 300;

    public function paydunya(Request $request): JsonResponse
    {
        ProcessPayDunyaWebhook::dispatch($request->all());

        return response()->json(['received' => true]);
    }

    /**
     * Callbacks PawaPay (deposits / payouts / refunds / checkouts).
     *
     * PawaPay poste le statut final d'une opération. On répond 200 le plus vite
     * possible (tout non-2xx déclenche des retentatives) et on traite en file.
     * Le job re-vérifie systématiquement le statut auprès de l'API : le payload
     * n'est qu'un déclencheur, jamais la source de vérité.
     */
    public function pawapay(Request $request, string $type = 'deposits'): JsonResponse
    {
        ProcessPawaPayCallback::dispatch($type, $request->all());

        return response()->json(['received' => true]);
    }

    /**
     * Webhook DocuSeal (signature électronique auto-hébergée).
     *
     * Sécurité, deux couches :
     *  1. Signature HMAC (en-tête `X-Docuseal-Signature`) vérifiée ci-dessous
     *     lorsqu'un secret est configuré.
     *  2. Le payload n'est qu'un DÉCLENCHEUR : le statut réel est de toute
     *     façon re-vérifié auprès de l'API DocuSeal avant toute écriture.
     *
     * Événements utiles : form.completed, form.declined, submission.completed,
     * submission.expired.
     */
    public function docuseal(Request $request, ConventionSignatureService $signatures): JsonResponse
    {
        if (!$this->docusealSignatureValid($request)) {
            return response()->json(['received' => false], 200);
        }

        $payload = $request->all();
        $event   = (string) ($payload['event_type'] ?? '');

        // `data` porte un submitter (form.*) ou une submission (submission.*).
        $submissionId = data_get($payload, 'data.submission_id')
            ?? data_get($payload, 'data.submission.id')
            ?? data_get($payload, 'data.id');

        $externalId = data_get($payload, 'data.external_id')
            ?? data_get($payload, 'data.submission.external_id');

        $investment = null;
        if ($submissionId) {
            $investment = Investment::where('signature_provider', 'docuseal')
                ->where('signature_request_id', (string) $submissionId)
                ->first();
        }
        if (!$investment && is_string($externalId) && str_starts_with($externalId, 'investment-')) {
            $investment = Investment::find((int) substr($externalId, strlen('investment-')));
        }

        if (!$investment) {
            Log::info('docuseal.webhook_unmatched', [
                'event'         => $event,
                'submission_id' => $submissionId,
            ]);

            return response()->json(['received' => true]);
        }

        try {
            $signatures->syncStatus($investment);
        } catch (\Throwable $e) {
            Log::warning('docuseal.webhook_sync_failed', [
                'investment_id' => $investment->id,
                'event'         => $event,
                'message'       => $e->getMessage(),
            ]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Vérifie l'en-tête `X-Docuseal-Signature: <timestamp>.<hmac_sha256_hex>`.
     *
     * DocuSeal signe la chaîne « {timestamp}.{corps brut} » en HMAC-SHA256 avec
     * le secret complet, préfixe `whsec_` INCLUS, et tolère 5 minutes de
     * décalage d'horloge (cf. lib/webhook_urls/signatures.rb du dépôt DocuSeal).
     *
     * Sans secret configuré, on laisse passer : la re-vérification du statut
     * auprès de l'API reste la garantie de fond.
     */
    private function docusealSignatureValid(Request $request): bool
    {
        $secret = (string) config('docuseal.webhook_secret');

        // Fermé par défaut : sans secret, on ne traite rien. L'inverse — « pas
        // de secret, on laisse passer » — transforme une variable d'environnement
        // oubliée en endpoint ouvert, sans le moindre signal. Le coût de ce
        // choix est assumé : une configuration incomplète fait taire le webhook
        // au lieu de l'exposer. `php artisan docuseal:check` le dit.
        if ($secret === '') {
            Log::warning('docuseal.webhook_rejected_no_secret', [
                'ip'     => $request->ip(),
                'reason' => 'DOCUSEAL_WEBHOOK_SECRET absent — webhook refusé',
            ]);

            return false;
        }

        $header = (string) $request->header('X-Docuseal-Signature', '');
        [$timestamp, $signature] = array_pad(explode('.', $header, 2), 2, null);

        if (!ctype_digit((string) $timestamp) || !$signature) {
            Log::warning('docuseal.webhook_bad_signature', ['reason' => 'malformed', 'ip' => $request->ip()]);

            return false;
        }

        // Fenêtre anti-rejeu de ±5 minutes, comme l'émetteur.
        if (abs(time() - (int) $timestamp) > self::DOCUSEAL_SIGNATURE_TOLERANCE) {
            Log::warning('docuseal.webhook_bad_signature', ['reason' => 'timestamp', 'ip' => $request->ip()]);

            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret);

        if (!hash_equals($expected, $signature)) {
            Log::warning('docuseal.webhook_bad_signature', ['reason' => 'mismatch', 'ip' => $request->ip()]);

            return false;
        }

        return true;
    }

    /**
     * Webhook Yousign (signature électronique).
     *
     * Yousign n'est plus le prestataire actif — DocuSeal l'a remplacé — mais la
     * route est CONSERVÉE : `SIGNATURE_PROVIDER=yousign` suffit à y revenir, et
     * les conventions déjà envoyées chez Yousign continuent d'y être suivies.
     *
     * Elle est en revanche fermée par défaut. Tant que YOUSIGN_WEBHOOK_SECRET
     * est vide — c'est le cas aujourd'hui — aucun appel n'est traité. Sans cela,
     * un prestataire désactivé laissait derrière lui un endpoint public capable
     * de déclencher des appels sortants.
     */
    public function yousign(Request $request, ConventionSignatureService $signatures): JsonResponse
    {
        $secret = (string) config('yousign.webhook_secret');

        if ($secret === '') {
            Log::warning('yousign.webhook_rejected_no_secret', [
                'ip'     => $request->ip(),
                'reason' => 'YOUSIGN_WEBHOOK_SECRET absent — prestataire inactif',
            ]);

            return response()->json(['received' => false], 200);
        }

        $sig = (string) $request->header('X-Yousign-Signature-256', '');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
        if (!hash_equals($expected, $sig)) {
            Log::warning('yousign.webhook_bad_signature', ['ip' => $request->ip()]);

            return response()->json(['received' => false], 200);
        }

        $requestId = data_get($request->all(), 'data.signature_request.id')
            ?? data_get($request->all(), 'signature_request.id');

        if ($requestId) {
            $investment = Investment::where('signature_request_id', $requestId)->first();
            if ($investment) {
                try {
                    $signatures->syncStatus($investment);
                } catch (\Throwable $e) {
                    Log::warning('yousign.webhook_sync_failed', [
                        'investment_id' => $investment->id,
                        'message'       => $e->getMessage(),
                    ]);
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
