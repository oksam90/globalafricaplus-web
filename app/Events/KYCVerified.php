<?php

namespace App\Events;

use App\Models\KYCVerification;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 4 — fired after a Smile callback approves a verification and the
 * user's tier has been upgraded (or refreshed). Listeners:
 *   - SendKYCNotification  → email + database
 *   - UnlockKYCFeatures    → audit log / cache busting hook
 */
class KYCVerified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public KYCVerification $verification,
        public string $tierGranted,
    ) {}
}
