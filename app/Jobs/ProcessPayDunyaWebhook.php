<?php

namespace App\Jobs;

use App\Models\Installment;
use App\Models\PaymentLog;
use App\Models\Transaction;
use App\Services\Payment\DTOs\PaymentStatus;
use App\Services\Payment\Gateways\PayDunyaGateway;
use App\Services\Payment\InstallmentService;
use App\Services\Payment\InvestmentService;
use App\Services\Payment\SubscriptionService;
use App\Services\Payment\TrainingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async processing of a PayDunya IPN webhook.
 *
 * The webhook signature has already been verified by VerifyPayDunyaWebhook
 * middleware before this job is queued. Here we:
 *  1. Resolve the Transaction by token or custom_data.transaction_id
 *  2. Double-check status with PayDunya (source of truth)
 *  3. Delegate business logic to SubscriptionService / InvestmentService / etc.
 */
class ProcessPayDunyaWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 30;

    public function __construct(
        public array $payload,
    ) {}

    public function handle(
        PayDunyaGateway     $gateway,
        SubscriptionService $subscriptions,
        InvestmentService   $investments,
        TrainingService     $trainings,
        InstallmentService  $installments,
    ): void {
        $data       = $this->payload['data'] ?? [];
        $invoice    = $data['invoice'] ?? [];
        $customData = $data['custom_data'] ?? [];
        $token      = $invoice['token'] ?? null;

        if (!$token) {
            Log::warning('PayDunya webhook payload missing invoice.token', ['payload' => $this->payload]);
            return;
        }

        // Resolve the transaction.
        $transaction = Transaction::where('paydunya_token', $token)->first();
        if (!$transaction && !empty($customData['transaction_id'])) {
            $transaction = Transaction::find($customData['transaction_id']);
        }

        if (!$transaction) {
            Log::warning('PayDunya webhook: transaction not found', [
                'token' => $token,
                'custom_data' => $customData,
            ]);
            return;
        }

        // Source-of-truth check with PayDunya API.
        $status = $gateway->verifyPayment($token);

        PaymentLog::create([
            'transaction_id'  => $transaction->id,
            'gateway'         => 'paydunya',
            'event_type'      => 'ipn.processed',
            'direction'       => 'inbound',
            'payload'         => ['verified_status' => $status->status, 'gateway_status' => $status->raw],
            'gateway_reference' => $token,
            'status_code'     => 200,
            'signature_valid' => true,
            'created_at'      => now(),
        ]);

        $paymentType = $transaction->payment_type;
        $isInstallment = !empty($customData['installment_id']);

        try {
            // Installment payments share their payment_type with the parent (investment/subscription/training)
            // but are tracked individually — settle the installment row first, then let the parent handler
            // decide whether the underlying record should activate.
            if ($isInstallment && $status->isPaid()) {
                $installment = Installment::find($customData['installment_id']);
                if ($installment) {
                    $installments->markPaid($installment, $transaction);
                }
            }

            match ($paymentType) {
                'subscription' => $status->isPaid()
                    ? $subscriptions->activate($transaction, $status)
                    : $this->markFailed($transaction, $status->status),

                'investment' => match (true) {
                    $status->isPaid()                                  => $investments->activate($transaction, $status),
                    $status->status === PaymentStatus::STATUS_REFUNDED => $investments->markRefunded($transaction),
                    default                                            => $this->markFailed($transaction, $status->status),
                },

                'training' => $status->isPaid()
                    ? $trainings->activate($transaction, $status)
                    : $this->markFailed($transaction, $status->status),

                default => Log::info('PayDunya webhook: payment_type not yet handled', [
                    'type' => $paymentType,
                    'transaction_id' => $transaction->id,
                ]),
            };
        } catch (\Throwable $e) {
            Log::error('PayDunya webhook processing failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            throw $e; // let the queue retry
        }
    }

    protected function markFailed(Transaction $transaction, string $gatewayStatus): void
    {
        if (in_array($transaction->status, ['completed', 'refunded'], true)) {
            return; // already finalised — don't downgrade
        }

        $transaction->update([
            'status'    => $gatewayStatus === 'cancelled' ? 'cancelled' : 'failed',
            'failed_at' => now(),
        ]);
    }
}
