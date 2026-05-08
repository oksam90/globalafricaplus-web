<?php

namespace App\Jobs;

use App\Models\KYCVerification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 4 — daily sweep to enforce the 24-month KYC validity window
 * (Directive UEMOA N° 02/2015, Art. 22 + spec § 12.2).
 *
 * For every approved verification whose `expires_at` lies in the past:
 *   1. Mark the verification row as `expired`.
 *   2. Downgrade the user back to `kyc_level = basic`.
 *   3. Clear the user's `kyc_verification_id` pointer.
 *
 * Wired in bootstrap/app.php → ->withSchedule() at 02:30 daily.
 */
class ExpireKYCVerification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Audit 2026-05 — KYC expiry sweep tied to UEMOA Art. 22 retry resilience.
    public int $tries = 3;
    public int $backoff = 120;
    public int $timeout = 600;

    public function handle(): void
    {
        Log::info('ExpireKYCVerification sweep starting');

        $tally = ['verifications_expired' => 0, 'users_downgraded' => 0];

        $expired = KYCVerification::where('status', 'approved')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $kv) {
            DB::transaction(function () use ($kv, &$tally) {
                $kv->update(['status' => 'expired']);
                $tally['verifications_expired']++;

                // Only downgrade users who are still pointed at THIS verification.
                $user = User::where('id', $kv->user_id)
                    ->where('kyc_verification_id', $kv->id)
                    ->first();

                if ($user && $user->kyc_level !== 'basic') {
                    $user->update([
                        'kyc_level'           => 'basic',
                        'kyc_verification_id' => null,
                    ]);
                    $tally['users_downgraded']++;
                }
            });
        }

        Log::info('ExpireKYCVerification sweep finished', $tally);
    }
}
