<?php

namespace App\Jobs;

use App\Events\KYCRejected;
use App\Events\KYCVerified;
use App\Models\KYCVerification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 2 — async processing of an inbound Smile Identity webhook (spec § 7).
 *
 * The webhook signature has already been verified by VerifySmileSignature.
 * Here we:
 *   1. Locate the matching KYCVerification row by partner_job_id (our UUID).
 *   2. Idempotency-guard: skip if already finalised.
 *   3. Persist Smile's result (code, text, confidence, actions, raw payload).
 *   4. Compute the new tier and mirror it onto the user, with kyc_expires_at
 *      set to now()+24 months on approval.
 *   5. Mark the verification as approved/rejected/processing.
 */
class ProcessSmileCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $backoff = 30;

    public function __construct(
        public array $payload,
    ) {}

    public function handle(): void
    {
        $params       = (array) ($this->payload['PartnerParams'] ?? []);
        $partnerJobId = (string) ($params['job_id'] ?? '');
        $smileJobId   = (string) ($this->payload['SmileJobID'] ?? '');
        $resultCode   = (string) ($this->payload['ResultCode'] ?? '');
        $resultText   = (string) ($this->payload['ResultText'] ?? '');
        $confidence   = $this->payload['ConfidenceValue'] ?? null;
        $actions      = (array)  ($this->payload['Actions'] ?? []);
        $jobType      = (int)    ($params['job_type'] ?? 0);

        if ($partnerJobId === '') {
            Log::warning('Smile callback: no PartnerParams.job_id, dropping.', ['payload' => $this->payload]);
            return;
        }

        $verification = KYCVerification::where('partner_job_id', $partnerJobId)->first();
        if (!$verification) {
            Log::warning('Smile callback: KYCVerification not found', [
                'partner_job_id' => $partnerJobId,
                'smile_job_id'   => $smileJobId,
            ]);
            return;
        }

        // Idempotency — once we've reached a terminal state, ignore re-deliveries.
        if (in_array($verification->status, ['approved', 'rejected', 'expired'], true)) {
            Log::info('Smile callback: already finalised, skipping', [
                'partner_job_id' => $partnerJobId,
                'status'         => $verification->status,
            ]);
            return;
        }

        $newStatus  = $this->mapStatus($resultCode);
        $tierGranted = $newStatus === 'approved'
            ? $this->determineTier($resultCode, $jobType, $confidence)
            : null;

        DB::transaction(function () use ($verification, $smileJobId, $resultCode, $resultText, $confidence, $actions, $newStatus, $tierGranted) {
            $verification->update([
                'smile_job_id'      => $smileJobId ?: $verification->smile_job_id,
                'result_code'       => $resultCode,
                'result_text'       => $resultText,
                'confidence_value'  => is_numeric($confidence) ? (float) $confidence : null,
                'actions'           => $actions ?: null,
                'status'            => $newStatus,
                'kyc_level_granted' => $tierGranted,
                'callback_payload'  => $this->payload,
                'completed_at'      => now(),
                'expires_at'        => $newStatus === 'approved' ? now()->addMonths((int) config('smile.kyc_expiry_months', 24)) : null,
            ]);

            // Reflect the verification onto the user.
            if ($newStatus === 'approved' && $tierGranted !== null) {
                $user = User::find($verification->user_id);
                if ($user) {
                    // Never downgrade a higher existing tier silently — only upgrade.
                    $rank = ['basic' => 0, 'verified' => 1, 'certified' => 2];
                    $currentRank = $rank[$user->kyc_level] ?? 0;
                    $newRank     = $rank[$tierGranted] ?? 0;

                    $user->update([
                        'kyc_level'           => $newRank > $currentRank ? $tierGranted : $user->kyc_level,
                        'kyc_verified_at'    => now(),
                        'kyc_expires_at'     => now()->addMonths((int) config('smile.kyc_expiry_months', 24)),
                        'kyc_verification_id' => $verification->id,
                        'selfie_registered'  => $user->selfie_registered || $this->wasSelfieRegistered($actions),
                    ]);
                }
            }
        });

        // Sprint 4 — fire domain events outside the transaction so listeners
        // see the persisted state.
        $verification->refresh();
        $user = User::find($verification->user_id);
        if (!$user) {
            return;
        }

        if ($newStatus === 'approved' && $tierGranted !== null) {
            KYCVerified::dispatch($user, $verification, $tierGranted);
        } elseif ($newStatus === 'rejected') {
            KYCRejected::dispatch($user, $verification, $resultCode, $resultText ?: null);
        }
    }

    /**
     * Map a Smile ResultCode to one of our KYCVerification.status values.
     */
    protected function mapStatus(string $code): string
    {
        return match (true) {
            in_array($code, ['0810', '0811'], true) => 'approved',
            in_array($code, ['0812', '0913'], true) => 'rejected',
            in_array($code, ['0814', '0915'], true) => 'processing',
            in_array($code, ['0914'], true)         => 'rejected',
            default                                  => 'processing',
        };
    }

    /**
     * Determine which tier to grant based on the result code and job type
     * (per spec § 7.1::determineKYCLevel). Biometric+high confidence = certified.
     */
    protected function determineTier(string $code, int $jobType, mixed $confidence): string
    {
        $confidenceFloat = is_numeric($confidence) ? (float) $confidence : 0.0;

        return match (true) {
            $code === '0810' && $jobType === 1 && $confidenceFloat >= 90 => 'certified',
            $code === '0810' && $jobType === 6                            => 'certified',
            $code === '0810' && $jobType === 5                            => 'verified',
            $code === '0811'                                              => 'verified',
            default                                                       => 'basic',
        };
    }

    protected function wasSelfieRegistered(array $actions): bool
    {
        $val = $actions['Register_Selfie'] ?? null;
        return is_string($val) && in_array(strtolower($val), ['approved', 'passed'], true);
    }
}
