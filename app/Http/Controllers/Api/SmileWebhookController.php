<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSmileCallback;
use App\Models\KYCVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 2 — entrypoint for inbound Smile Identity callbacks (spec § 7.1).
 *
 * Signature verification has already happened in `VerifySmileSignature`
 * middleware before this controller is reached. We:
 *
 *   1. Quick idempotence check by SmileJobID (acks dupes with 200).
 *   2. Dispatch ProcessSmileCallback to apply the result asynchronously.
 *   3. Always reply 200 OK once accepted, so Smile doesn't retry-storm us.
 */
class SmileWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload    = $request->all();
        $smileJobId = (string) ($payload['SmileJobID'] ?? '');
        $params     = (array) ($payload['PartnerParams'] ?? []);
        $partnerJobId = (string) ($params['job_id'] ?? '');

        Log::info('Smile webhook received', [
            'smile_job_id'   => $smileJobId,
            'partner_job_id' => $partnerJobId,
            'job_type'       => $params['job_type'] ?? null,
            'result_code'    => $payload['ResultCode'] ?? null,
        ]);

        if ($partnerJobId === '' && $smileJobId === '') {
            return response()->json(['message' => 'Missing job identifiers.'], 422);
        }

        // Quick idempotence — if we already finalised this job, ack and skip.
        $existing = $partnerJobId !== ''
            ? KYCVerification::where('partner_job_id', $partnerJobId)->first()
            : KYCVerification::where('smile_job_id', $smileJobId)->first();

        if ($existing && in_array($existing->status, ['approved', 'rejected', 'expired'], true)) {
            return response()->json(['message' => 'Already processed.'], 200);
        }

        ProcessSmileCallback::dispatch($payload);

        return response()->json(['message' => 'OK'], 200);
    }
}
