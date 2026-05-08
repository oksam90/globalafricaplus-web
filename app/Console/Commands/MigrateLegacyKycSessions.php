<?php

namespace App\Console\Commands;

use App\Models\KYCVerification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One-shot migration of legacy IDnorm `kyc_sessions` rows to the Smile-shaped
 * `kyc_verifications` table. Run BEFORE the drop migration.
 *
 *   php artisan kyc:migrate-sessions          # dry-run (counts only)
 *   php artisan kyc:migrate-sessions --apply  # actually writes
 *
 * Idempotency: rows with `partner_job_id = "legacy:{kyc_sessions.id}"` are
 * skipped on subsequent runs.
 */
class MigrateLegacyKycSessions extends Command
{
    protected $signature = 'kyc:migrate-sessions {--apply : Persist changes (default is dry-run)}';
    protected $description = 'Copy verified rows from kyc_sessions into kyc_verifications, then report.';

    public function handle(): int
    {
        if (!Schema::hasTable('kyc_sessions')) {
            $this->info('kyc_sessions table does not exist — nothing to migrate.');
            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');

        $sessions = DB::table('kyc_sessions')
            ->whereIn('status', ['verified', 'rejected', 'documents_submitted'])
            ->get();

        $this->info(sprintf('Found %d legacy sessions to consider.', $sessions->count()));
        $tally = ['imported' => 0, 'skipped_existing' => 0, 'skipped_no_user' => 0, 'rejected' => 0];

        foreach ($sessions as $row) {
            $partnerJobId = "legacy:{$row->id}";

            if (KYCVerification::where('partner_job_id', $partnerJobId)->exists()) {
                $tally['skipped_existing']++;
                continue;
            }

            $user = User::find($row->user_id);
            if (!$user) {
                $tally['skipped_no_user']++;
                continue;
            }

            $isApproved = $row->status === 'verified';
            $isRejected = $row->status === 'rejected';

            $payload = [
                'user_id'           => $user->id,
                'smile_job_id'      => null,
                'partner_job_id'    => $partnerJobId,
                'job_type'          => 'basic_kyc',
                'country'           => substr(strtoupper((string) ($row->country ?? 'SN')), 0, 2),
                'id_type'           => $this->mapDocType($row->document_type),
                'id_number_hash'    => $row->document_number
                    ? KYCVerification::hashIdNumber((string) $row->document_number)
                    : hash_hmac('sha256', "legacy-{$row->id}", (string) config('app.key')),
                'result_code'       => $isApproved ? '0810' : ($isRejected ? '0812' : null),
                'result_text'       => $isApproved ? 'Approved (legacy import)' : ($isRejected ? 'Rejected (legacy import)' : 'Imported from kyc_sessions'),
                'kyc_level_granted' => $isApproved ? 'verified' : null,
                'status'            => $isApproved ? 'approved' : ($isRejected ? 'rejected' : 'processing'),
                'callback_payload'  => [
                    'imported_from' => 'kyc_sessions',
                    'legacy_id'     => $row->id,
                    'legacy_status' => $row->status,
                    'imported_at'   => now()->toIso8601String(),
                ],
                'expires_at'        => $isApproved
                    ? ($row->verified_at
                        ? \Illuminate\Support\Carbon::parse($row->verified_at)->addMonths(24)
                        : now()->addMonths(24))
                    : null,
                'submitted_at'      => $row->created_at ?? now(),
                'completed_at'      => $row->verified_at,
            ];

            if ($apply) {
                DB::transaction(function () use ($payload, $user, $isApproved) {
                    $kv = KYCVerification::create($payload);

                    // Only upgrade the user if the existing tier is lower than 'verified'
                    // (defensive — Smile-driven verifications take precedence).
                    if ($isApproved) {
                        $rank = ['none' => -1, 'basic' => 0, 'verified' => 1, 'certified' => 2];
                        if (($rank[$user->kyc_level] ?? -1) < 1) {
                            $user->update([
                                'kyc_level'           => 'verified',
                                'kyc_verified_at'     => $kv->completed_at ?? now(),
                                'kyc_expires_at'      => $kv->expires_at,
                                'kyc_verification_id' => $user->kyc_verification_id ?: $kv->id,
                            ]);
                        }
                    }
                });
            }

            $tally[$isRejected ? 'rejected' : 'imported']++;
        }

        $this->newLine();
        $this->info($apply ? '✓ Apply mode — changes written.' : '⓪ Dry-run — re-run with --apply to persist.');
        $this->table(
            ['Imported', 'Rejected', 'Skipped (existing)', 'Skipped (no user)'],
            [[$tally['imported'], $tally['rejected'], $tally['skipped_existing'], $tally['skipped_no_user']]],
        );

        return self::SUCCESS;
    }

    /**
     * Map legacy enum (cni/passport/permis/carte_sejour) to a Smile id_type.
     */
    protected function mapDocType(?string $legacy): string
    {
        return match ($legacy) {
            'passport'      => 'PASSPORT',
            'permis'        => 'DRIVERS_LICENSE',
            'carte_sejour'  => 'ALIEN_CARD',
            default         => 'NATIONAL_ID', // covers 'cni' + null
        };
    }
}
