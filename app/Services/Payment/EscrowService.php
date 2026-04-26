<?php

namespace App\Services\Payment;

use App\Jobs\ProcessEscrowRelease;
use App\Models\EscrowMilestone;
use App\Models\Investment;
use App\Models\PaymentLog;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payment\DTOs\DisburseResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sprint 4 — Orchestrates the escrow lifecycle for a milestone-based investment.
 *
 *  Flow:
 *    1. Entrepreneur submits proof for a milestone   (submitMilestone)
 *    2. Investor approves or rejects                 (approveMilestone / rejectMilestone)
 *    3. On approval, dispatch async release job which
 *       calls PayDunya DirectPay to credit the project owner's
 *       mobile-money account                         (releaseMilestone)
 *    4. A daily scheduled job refunds investors whose
 *       escrow has been stuck > N days with no
 *       milestone validated                          (autoRefundExpiredEscrow)
 */
class EscrowService
{
    public function __construct(
        protected PaymentGatewayFactory $gatewayFactory,
    ) {}

    /**
     * Entrepreneur submits proof of milestone delivery.
     * Status: pending → in_review.
     *
     * @param array $evidence Free-form payload (URLs, file ids, notes).
     */
    public function submitMilestone(EscrowMilestone $milestone, User $entrepreneur, array $evidence = []): EscrowMilestone
    {
        $project = $milestone->project;
        if (!$project || $project->user_id !== $entrepreneur->id) {
            throw new RuntimeException("Vous n'êtes pas le propriétaire de ce projet.");
        }

        if (!in_array($milestone->status, ['pending', 'rejected'], true)) {
            throw new RuntimeException("Ce jalon ne peut plus être soumis (statut : {$milestone->status}).");
        }

        $milestone->update([
            'status'   => 'in_review',
            'evidence' => $evidence,
        ]);

        return $milestone->fresh();
    }

    /**
     * Investor approves a milestone. Triggers async release via PayDunya DirectPay.
     */
    public function approveMilestone(EscrowMilestone $milestone, User $investor): EscrowMilestone
    {
        $investment = $milestone->investment;
        if (!$investment || $investment->investor_id !== $investor->id) {
            throw new RuntimeException("Vous n'êtes pas l'investisseur de ce jalon.");
        }

        if ($milestone->status !== 'in_review') {
            throw new RuntimeException("Ce jalon n'est pas en attente de validation (statut : {$milestone->status}).");
        }

        if ($investment->status !== 'escrow') {
            throw new RuntimeException("L'investissement n'est plus en séquestre (statut : {$investment->status}).");
        }

        DB::transaction(function () use ($milestone) {
            $milestone->update([
                'status'      => 'approved',
                'approved_at' => now(),
            ]);
        });

        ProcessEscrowRelease::dispatch($milestone->id);

        return $milestone->fresh();
    }

    /**
     * Investor rejects a milestone (reverts to pending so entrepreneur can re-submit).
     */
    public function rejectMilestone(EscrowMilestone $milestone, User $investor, ?string $reason = null): EscrowMilestone
    {
        $investment = $milestone->investment;
        if (!$investment || $investment->investor_id !== $investor->id) {
            throw new RuntimeException("Vous n'êtes pas l'investisseur de ce jalon.");
        }

        if ($milestone->status !== 'in_review') {
            throw new RuntimeException("Ce jalon n'est pas en attente de validation (statut : {$milestone->status}).");
        }

        $milestone->update([
            'status'      => 'rejected',
            'admin_notes' => $reason,
        ]);

        return $milestone->fresh();
    }

    /**
     * Effectively credit the project owner's mobile-money account.
     * Idempotent: if already released, just returns the milestone.
     *
     * Called from the ProcessEscrowRelease job (async, with retries).
     */
    public function releaseMilestone(EscrowMilestone $milestone): EscrowMilestone
    {
        if ($milestone->status === 'released') {
            return $milestone;
        }

        if ($milestone->status !== 'approved') {
            throw new RuntimeException("Le jalon doit être approuvé avant libération (statut : {$milestone->status}).");
        }

        $project = $milestone->project;
        $investment = $milestone->investment;
        if (!$project || !$investment) {
            throw new RuntimeException('Projet ou investissement introuvable.');
        }

        if (empty($project->payout_phone)) {
            throw new RuntimeException("Le porteur de projet n'a pas configuré son numéro de paiement.");
        }

        $gateway = $this->gatewayFactory->forCountry(
            $project->payout_country ?: ($project->country ?: 'SN'),
        );

        // Convert milestone amount (project currency) → XOF for PayDunya disburse.
        $amountXof = $this->toXof((float) $milestone->amount, (string) $milestone->currency, $gateway);

        $transaction = DB::transaction(function () use ($milestone, $project, $investment, $amountXof, $gateway) {
            return Transaction::create([
                'user_id'           => $project->user_id,
                'transactable_type' => Project::class,
                'transactable_id'   => $project->id,
                'amount'            => $amountXof,
                'currency'          => 'XOF',
                'gateway'           => $gateway->getName(),
                'gateway_reference' => 'rel_' . Str::random(20),
                'payment_type'      => 'escrow_release',
                'status'            => 'processing',
                'customer_phone'    => $project->payout_phone,
                'customer_country'  => $project->payout_country,
                'description'      => "Libération jalon #{$milestone->position} — {$project->title}",
                'custom_data'      => [
                    'milestone_id'  => $milestone->id,
                    'investment_id' => $investment->id,
                    'project_id'    => $project->id,
                    'native_amount'   => (float) $milestone->amount,
                    'native_currency' => $milestone->currency,
                ],
            ]);
        });

        $result = $gateway->disburse(
            $project->payout_phone,
            $amountXof,
            $project->payout_provider ?: 'unknown',
        );

        $this->logDisburse($transaction, $result);

        if (!$result->success) {
            $transaction->update([
                'status'    => 'failed',
                'failed_at' => now(),
            ]);
            throw new RuntimeException($result->message ?: 'Échec du décaissement PayDunya.');
        }

        DB::transaction(function () use ($milestone, $transaction, $result, $investment) {
            $transaction->update([
                'status'            => 'completed',
                'paid_at'           => now(),
                'gateway_reference' => $result->disburseReference ?: $transaction->gateway_reference,
            ]);

            $milestone->update([
                'status'                 => 'released',
                'released_at'            => now(),
                'release_transaction_id' => $transaction->id,
            ]);

            $allReleased = $investment->milestones()
                ->where('status', '!=', 'released')
                ->doesntExist();

            if ($allReleased) {
                $investment->update(['status' => 'released']);
            }
        });

        return $milestone->fresh();
    }

    /**
     * Refund every investor whose escrow has been stuck for more than
     * `paydunya.disburse.auto_refund_days` days with no milestone released.
     *
     * Returns a tally for the cron summary log.
     */
    public function autoRefundExpiredEscrow(): array
    {
        $days = (int) config('paydunya.disburse.auto_refund_days', 90);
        $cutoff = now()->subDays($days);

        $stuck = Investment::query()
            ->where('status', 'escrow')
            ->where('paid_at', '<', $cutoff)
            ->whereDoesntHave('milestones', fn ($q) => $q->where('status', 'released'))
            ->get();

        $refunded = 0;
        $failed = 0;

        foreach ($stuck as $investment) {
            try {
                $this->refundInvestor($investment);
                $refunded++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Auto-refund failed', [
                    'investment_id' => $investment->id,
                    'message'       => $e->getMessage(),
                ]);
            }
        }

        Log::info('Escrow auto-refund sweep completed', [
            'cutoff_days' => $days,
            'scanned'     => $stuck->count(),
            'refunded'    => $refunded,
            'failed'      => $failed,
        ]);

        return [
            'scanned'  => $stuck->count(),
            'refunded' => $refunded,
            'failed'   => $failed,
        ];
    }

    /**
     * Refund a single investment via PayDunya DirectPay back to the investor's phone.
     */
    public function refundInvestor(Investment $investment): Investment
    {
        if ($investment->status === 'refunded') {
            return $investment;
        }

        if ($investment->status !== 'escrow') {
            throw new RuntimeException("L'investissement n'est pas en séquestre (statut : {$investment->status}).");
        }

        $originalTx = $investment->transaction;
        if (!$originalTx) {
            throw new RuntimeException("Transaction d'origine introuvable.");
        }

        $investorPhone = $originalTx->customer_phone ?: optional($investment->investor)->phone;
        if (empty($investorPhone)) {
            throw new RuntimeException("Numéro de l'investisseur indisponible pour le remboursement.");
        }

        $gateway = $this->gatewayFactory->make('paydunya');
        $amountXof = (float) ($investment->charged_amount ?: $this->toXof(
            (float) $investment->amount,
            (string) $investment->currency,
            $gateway,
        ));

        $refundTx = DB::transaction(function () use ($investment, $originalTx, $amountXof, $gateway, $investorPhone) {
            return Transaction::create([
                'user_id'           => $investment->investor_id,
                'transactable_type' => Project::class,
                'transactable_id'   => $investment->project_id,
                'amount'            => $amountXof,
                'currency'          => 'XOF',
                'gateway'           => $gateway->getName(),
                'gateway_reference' => 'ref_' . Str::random(20),
                'payment_type'      => 'refund',
                'status'            => 'processing',
                'customer_phone'    => $investorPhone,
                'customer_country'  => $originalTx->customer_country,
                'customer_name'     => $originalTx->customer_name,
                'customer_email'    => $originalTx->customer_email,
                'description'      => "Remboursement automatique — investissement #{$investment->id}",
                'custom_data'      => [
                    'investment_id'           => $investment->id,
                    'original_transaction_id' => $originalTx->id,
                    'reason'                  => 'auto_refund_expired_escrow',
                ],
            ]);
        });

        $result = $gateway->disburse($investorPhone, $amountXof, 'paydunya');
        $this->logDisburse($refundTx, $result);

        if (!$result->success) {
            $refundTx->update(['status' => 'failed', 'failed_at' => now()]);
            throw new RuntimeException($result->message ?: 'Échec du remboursement PayDunya.');
        }

        DB::transaction(function () use ($investment, $originalTx, $refundTx, $result) {
            $refundTx->update([
                'status'            => 'completed',
                'paid_at'           => now(),
                'gateway_reference' => $result->disburseReference ?: $refundTx->gateway_reference,
            ]);

            $project = $investment->project;
            if ($project) {
                $project->decrement('amount_raised', (float) $investment->amount);
            }

            $investment->update([
                'status'      => 'refunded',
                'refunded_at' => now(),
            ]);

            $originalTx->update([
                'status'      => 'refunded',
                'refunded_at' => now(),
            ]);

            // Cancel all not-yet-released milestones for this investment.
            EscrowMilestone::where('investment_id', $investment->id)
                ->whereNotIn('status', ['released'])
                ->update(['status' => 'cancelled']);
        });

        return $investment->fresh();
    }

    protected function toXof(float $amount, string $currency, PaymentGatewayInterface $gateway): float
    {
        $currency = strtoupper($currency);
        if ($currency === 'XOF' || $currency === 'XAF') {
            return (float) round($amount, 0);
        }
        $rate = $gateway->getExchangeRate($currency, 'XOF');
        return (float) round($amount * $rate, 0);
    }

    protected function logDisburse(Transaction $transaction, DisburseResult $result): void
    {
        try {
            PaymentLog::create([
                'transaction_id'    => $transaction->id,
                'gateway'           => $transaction->gateway,
                'event_type'        => $result->success ? 'disburse.success' : 'disburse.failed',
                'direction'         => 'outgoing',
                'gateway_reference' => $result->disburseReference,
                'payload'           => array_merge(
                    $result->raw,
                    ['message' => $result->message],
                ),
                'created_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write PaymentLog for disburse', [
                'transaction_id' => $transaction->id,
                'message'        => $e->getMessage(),
            ]);
        }
    }
}
