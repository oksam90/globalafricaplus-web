<?php

namespace App\Listeners;

use App\Events\AMLFlagged;
use App\Models\PaymentLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 4 — auto-emits a suspicious-activity declaration to CENTIF (Cellule
 * Nationale de Traitement des Informations Financières) when AML screening
 * surfaces sanctions or critical-risk matches.
 *
 * The actual CENTIF transmission is mocked in this iteration: we write an
 * immutable PaymentLog row and toggle `aml_screenings.auto_reported = true`.
 * A future sprint will wire the real CENTIF API once credentials are issued.
 */
class ReportSuspiciousActivity implements ShouldQueue
{
    public string $queue = 'compliance';

    public function handle(AMLFlagged $event): void
    {
        $screening = $event->screening;

        // Spec § 12.2 — auto-report on sanctions match or critical-risk classification.
        $shouldReport = $screening->sanctions_match || $screening->risk_level === 'critical';

        if (!$shouldReport || $screening->auto_reported) {
            return;
        }

        try {
            // 1. Mock the CENTIF transmission via an audit-grade log entry.
            // PaymentLog has $timestamps = false, so we set created_at explicitly
            // to stay portable across DB engines (SQLite vs. MySQL useCurrent()).
            PaymentLog::create([
                'gateway'           => 'centif',
                'event_type'        => 'compliance.suspicious_activity_report',
                'direction'         => 'outbound',
                'gateway_reference' => "screening:{$screening->id}",
                'status_code'       => 202, // Accepted (mocked)
                'signature_valid'   => true,
                'created_at'        => now(),
                'payload'           => [
                    'user_id'             => $event->user->id,
                    'user_name'           => $event->user->name,
                    'user_email'          => $event->user->email,
                    'screening_id'        => $screening->id,
                    'risk_level'          => $screening->risk_level,
                    'sanctions_match'     => $screening->sanctions_match,
                    'pep_match'           => $screening->pep_match,
                    'adverse_media_match' => $screening->adverse_media_match,
                    'screened_at'         => $screening->screened_at?->toIso8601String(),
                    'mode'                => 'mock', // remove once real CENTIF API is wired
                ],
            ]);

            // 2. Mark the screening as already reported to avoid duplicates.
            $screening->update(['auto_reported' => true]);

            // 3. Belt-and-suspenders: always block the user when sanctions hit.
            if ($screening->sanctions_match) {
                $event->user->update(['aml_status' => 'blocked']);
            }
        } catch (\Throwable $e) {
            Log::error('ReportSuspiciousActivity failed', [
                'screening_id' => $screening->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
