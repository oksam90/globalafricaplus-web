<?php

namespace App\Jobs;

use App\Models\EscrowMilestone;
use App\Services\Payment\EscrowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 4 — Async wrapper that calls EscrowService::releaseMilestone().
 *
 * The release talks to PayDunya DirectPay (network I/O) and creates a
 * Transaction row, so we run it off the request cycle and rely on the queue
 * to retry transient failures (PayDunya throttling, gateway 5xx).
 */
class ProcessEscrowRelease implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $milestoneId,
    ) {}

    public function handle(EscrowService $escrow): void
    {
        $milestone = EscrowMilestone::find($this->milestoneId);
        if (!$milestone) {
            Log::warning('ProcessEscrowRelease: milestone not found', [
                'milestone_id' => $this->milestoneId,
            ]);
            return;
        }

        if ($milestone->status === 'released') {
            return; // idempotent
        }

        $escrow->releaseMilestone($milestone);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessEscrowRelease permanently failed', [
            'milestone_id' => $this->milestoneId,
            'message'      => $e->getMessage(),
        ]);
    }
}
