<?php

namespace App\Listeners;

use App\Events\AMLFlagged;
use App\Events\KYCRejected;
use App\Events\KYCVerified;
use App\Models\User;
use App\Notifications\AMLFlaggedNotification;
use App\Notifications\KYCRejectedNotification;
use App\Notifications\KYCVerifiedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sprint 4 — single listener that fans out KYC-related events to the right
 * notification channel. Implements three handlers, registered via Laravel's
 * event auto-discovery.
 *
 * Channel routing:
 *   - KYCVerified / KYCRejected → notify the affected user (mail + db)
 *   - AMLFlagged                → notify all admins (mail + db)
 */
class SendKYCNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handleKYCVerified(KYCVerified $event): void
    {
        try {
            $event->user->notify(new KYCVerifiedNotification($event->verification, $event->tierGranted));
        } catch (\Throwable $e) {
            Log::warning('KYC verified notif failed', ['user_id' => $event->user->id, 'error' => $e->getMessage()]);
        }
    }

    public function handleKYCRejected(KYCRejected $event): void
    {
        try {
            $event->user->notify(new KYCRejectedNotification(
                $event->verification,
                $event->resultCode,
                $event->reason,
            ));
        } catch (\Throwable $e) {
            Log::warning('KYC rejected notif failed', ['user_id' => $event->user->id, 'error' => $e->getMessage()]);
        }
    }

    public function handleAMLFlagged(AMLFlagged $event): void
    {
        // Locate every user with the `admin` role — they receive the AML alert.
        $admins = User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->get();

        if ($admins->isEmpty()) {
            Log::warning('AMLFlagged: no admin users to notify', ['screening_id' => $event->screening->id]);
            return;
        }

        try {
            Notification::send($admins, new AMLFlaggedNotification(
                $event->screening,
                $event->user->name,
            ));
        } catch (\Throwable $e) {
            Log::warning('AML flagged notif failed', ['screening_id' => $event->screening->id, 'error' => $e->getMessage()]);
        }
    }
}
