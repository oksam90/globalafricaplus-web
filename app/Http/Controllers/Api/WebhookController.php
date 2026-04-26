<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPayDunyaWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
