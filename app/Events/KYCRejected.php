<?php

namespace App\Events;

use App\Models\KYCVerification;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 4 — fired when a Smile callback returns a final REJECTED status
 * (codes 0812 / 0913 / 0914). Triggers a user-facing notification with a
 * call-to-action to re-submit (and the document-verification fallback when
 * the rejection was an "ID Not Found" 0914).
 */
class KYCRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public KYCVerification $verification,
        public string $resultCode,
        public ?string $reason = null,
    ) {}
}
