<?php

namespace App\Jobs;

use App\Services\Payment\EscrowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 4 — Daily sweep: refund investors whose escrow has been stuck
 * past `paydunya.disburse.auto_refund_days` (default 90) with no
 * milestone released.
 *
 * Scheduled in bootstrap/app.php → ->withSchedule().
 */
class ProcessAutoRefund implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Audit 2026-05 — financial sweep: 3 retries with backoff so a transient
    // gateway / DB hiccup doesn't skip auto-refunds for a whole 24 h cycle.
    public int $tries = 3;
    public int $backoff = 120;
    public int $timeout = 600;

    public function handle(EscrowService $escrow): void
    {
        Log::info('ProcessAutoRefund sweep starting');
        $tally = $escrow->autoRefundExpiredEscrow();
        Log::info('ProcessAutoRefund sweep finished', $tally);
    }
}
