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

    public int $tries = 1;
    public int $timeout = 600; // a sweep over many investments may take a while

    public function handle(EscrowService $escrow): void
    {
        Log::info('ProcessAutoRefund sweep starting');
        $tally = $escrow->autoRefundExpiredEscrow();
        Log::info('ProcessAutoRefund sweep finished', $tally);
    }
}
