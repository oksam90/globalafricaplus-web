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
     * Webhook Yousign (signature électronique).
     *
     * Vérifie la signature HMAC-SHA256 si un secret est configuré, puis
     * synchronise l'investissement concerné (récupère le PDF signé quand prêt).
     * Toujours 200 pour éviter les retentatives inutiles.
     */
    public function yousign(Request $request, ConventionSignatureService $signatures): JsonResponse
    {
        $secret = (string) config('yousign.webhook_secret');
        if ($secret !== '') {
            $sig = (string) $request->header('X-Yousign-Signature-256', '');
            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
            if (!hash_equals($expected, $sig)) {
                Log::warning('yousign.webhook_bad_signature', ['ip' => $request->ip()]);
                return response()->json(['received' => false], 200);
            }
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
