<?php

namespace App\Console\Commands;

use App\Models\KYCVerification;
use App\Notifications\KYCVerifiedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Audit 2026-05 — one-shot backfill: write a KYC-verified database notification
 * for every verification created by the legacy import
 * (`partner_job_id LIKE 'legacy:%'`).
 *
 * Why direct notification instead of dispatching the KYCVerified event:
 *   - SendKYCNotification listener implements ShouldQueue, so going through
 *     the event would queue the notification — and it never lands in the DB
 *     if no queue worker is running on the VPS.
 *   - Calling `notifyNow()` skips the queue entirely and writes the
 *     `notifications` row synchronously in the same DB transaction.
 *   - Idempotent: skips users who already have a `kyc_verified` notif tied
 *     to this verification (matched by JSON payload `verification_id`).
 *
 *   php artisan kyc:notify-legacy-imported          # dry-run (counts only)
 *   php artisan kyc:notify-legacy-imported --apply  # actually write notifications
 */
class NotifyLegacyImportedKyc extends Command
{
    protected $signature = 'kyc:notify-legacy-imported {--apply : Actually write notifications}';
    protected $description = 'Synchronously emit a KYCVerifiedNotification for every legacy-imported user.';

    public function handle(): int
    {
        $verifications = KYCVerification::query()
            ->where('partner_job_id', 'like', 'legacy:%')
            ->where('status', 'approved')
            ->whereNotNull('kyc_level_granted')
            ->with('user')
            ->get();

        $this->info(sprintf('Found %d legacy approved verification(s).', $verifications->count()));
        $apply = (bool) $this->option('apply');

        $sent = 0;
        $skipped = 0;
        foreach ($verifications as $kv) {
            if (!$kv->user) {
                $this->warn("  → user #{$kv->user_id} missing for verification {$kv->id}, skipping");
                continue;
            }

            // Idempotence: don't double-notify if the notification already exists.
            $exists = $kv->user->notifications()
                ->where('type', KYCVerifiedNotification::class)
                ->whereJsonContains('data->verification_id', $kv->id)
                ->exists();

            if ($exists) {
                $this->line("  → already notified: {$kv->user->email}");
                $skipped++;
                continue;
            }

            $this->line("  → notify {$kv->user->email} (tier={$kv->kyc_level_granted})");
            if ($apply) {
                // notifyNow() bypasses the queue — the database row is written
                // before the call returns, regardless of QUEUE_CONNECTION.
                Notification::sendNow(
                    $kv->user,
                    new KYCVerifiedNotification($kv, $kv->kyc_level_granted),
                );
            }
            $sent++;
        }

        $this->newLine();
        $this->table(
            ['Sent', 'Skipped (already notified)'],
            [[$sent, $skipped]],
        );

        if ($apply) {
            $this->info("✓ Backfill complete. Refresh the user's session — bell should now show « KYC Vérifié ».");
        } else {
            $this->info("Dry-run — {$sent} notification(s) would be written. Re-run with --apply.");
        }

        return self::SUCCESS;
    }
}
