<?php

namespace App\Jobs;

use App\Services\Payment\InstallmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 5 — Daily sweep: invoice the next due installment for every active plan
 * whose next_due_at has arrived.
 *
 * Scheduled in bootstrap/app.php → ->withSchedule().
 */
class ProcessInstallmentDue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function handle(InstallmentService $installments): void
    {
        Log::info('ProcessInstallmentDue sweep starting');
        $tally = $installments->processDueSweep();
        Log::info('ProcessInstallmentDue sweep finished', $tally);
    }
}
