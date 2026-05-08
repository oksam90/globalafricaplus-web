<?php

namespace App\Console\Commands;

use App\Events\KYCVerified;
use App\Models\KYCVerification;
use Illuminate\Console\Command;

/**
 * Audit 2026-05 — one-shot backfill: dispatch a KYCVerified event for every
 * verification created by the legacy import (`partner_job_id LIKE 'legacy:%'`).
 *
 * Why: ProcessSmileCallback fires KYCVerified during the live Smile flow, but
 * the import path bypasses it — those users never received the welcome
 * notification ("KYC Vérifié"). This command re-emits the event so the bell
 * dropdown shows a confirmation entry.
 *
 *   php artisan kyc:notify-legacy-imported          # dry-run (counts only)
 *   php artisan kyc:notify-legacy-imported --apply  # actually dispatch
 */
class NotifyLegacyImportedKyc extends Command
{
    protected $signature = 'kyc:notify-legacy-imported {--apply : Actually dispatch the events}';
    protected $description = 'Re-emit KYCVerified for users imported from kyc_sessions.';

    public function handle(): int
    {
        $verifications = KYCVerification::query()
            ->where('partner_job_id', 'like', 'legacy:%')
            ->where('status', 'approved')
            ->whereNotNull('kyc_level_granted')
            ->with('user')
            ->get();

        $this->info(sprintf('Found %d legacy approved verifications.', $verifications->count()));
        $apply = (bool) $this->option('apply');

        $count = 0;
        foreach ($verifications as $kv) {
            if (!$kv->user) {
                $this->warn("  → user #{$kv->user_id} missing for verification {$kv->id}, skipping");
                continue;
            }
            $this->line("  → dispatch KYCVerified for {$kv->user->email} (tier={$kv->kyc_level_granted})");
            if ($apply) {
                KYCVerified::dispatch($kv->user, $kv, $kv->kyc_level_granted);
            }
            $count++;
        }

        $this->newLine();
        if ($apply) {
            $this->info("✓ Dispatched {$count} KYCVerified event(s). Notifications will land via SendKYCNotification listener.");
        } else {
            $this->info("Dry-run — {$count} event(s) would be dispatched. Re-run with --apply.");
        }

        return self::SUCCESS;
    }
}
