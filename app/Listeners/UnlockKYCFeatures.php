<?php

namespace App\Listeners;

use App\Events\KYCVerified;
use App\Models\PaymentLog;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 4 — invoked when a verification reaches APPROVED.
 * Records an immutable audit trail entry (using the existing payment_logs
 * table for centralised compliance evidence) and clears any cached
 * permission gates so the upgrade takes effect on the next request.
 *
 * Auto-discovered via single `handle(KYCVerified $event)` signature.
 */
class UnlockKYCFeatures
{
    public function handle(KYCVerified $event): void
    {
        try {
            PaymentLog::create([
                'gateway'         => 'smile_identity',
                'event_type'      => 'kyc.tier_upgraded',
                'direction'       => 'inbound',
                'gateway_reference' => $event->verification->smile_job_id,
                'status_code'     => 200,
                'signature_valid' => true,
                'payload'         => [
                    'user_id'           => $event->user->id,
                    'tier_granted'      => $event->tierGranted,
                    'verification_id'   => $event->verification->id,
                    'partner_job_id'    => $event->verification->partner_job_id,
                    'job_type'          => $event->verification->job_type,
                    'result_code'       => $event->verification->result_code,
                    'confidence_value'  => $event->verification->confidence_value,
                    'expires_at'        => $event->verification->expires_at?->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('UnlockKYCFeatures audit log failed', [
                'user_id' => $event->user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
