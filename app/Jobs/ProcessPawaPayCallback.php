<?php

namespace App\Jobs;

use App\Models\EscrowMilestone;
use App\Models\Installment;
use App\Models\PaymentLog;
use App\Models\Transaction;
use App\Services\Payment\DTOs\PaymentStatus;
use App\Services\Payment\Gateways\PawaPayGateway;
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
 * Traitement asynchrone d'un callback PawaPay.
 *
 * Principe (identique à ProcessPayDunyaWebhook) : le payload sert uniquement à
 * identifier l'opération ; le statut est ensuite RE-VÉRIFIÉ auprès de l'API
 * PawaPay, seule source de vérité. Cela rend le traitement insensible à un
 * callback rejoué, tronqué ou forgé.
 *
 * `type` ∈ deposits | payouts | refunds | checkouts
 */
class ProcessPawaPayCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 30;

    public function __construct(
        public string $type,
        public array $payload,
    ) {}

    public function handle(
        PawaPayGateway      $gateway,
        SubscriptionService $subscriptions,
        InvestmentService   $investments,
        TrainingService     $trainings,
        InstallmentService  $installments,
    ): void {
        match ($this->type) {
            'payouts' => $this->handlePayout(),
            'refunds' => $this->handleRefund(),
            default   => $this->handleDeposit($gateway, $subscriptions, $investments, $trainings, $installments),
        };
    }

    // ─────────────────────────── dépôts ───────────────────────────

    protected function handleDeposit(
        PawaPayGateway      $gateway,
        SubscriptionService $subscriptions,
        InvestmentService   $investments,
        TrainingService     $trainings,
        InstallmentService  $installments,
    ): void {
        $depositId = $this->id('depositId');

        if (!$depositId) {
            Log::warning('pawapay.callback_missing_deposit_id', ['type' => $this->type]);
            return;
        }

        $transaction = $this->resolveTransaction($depositId);
        if (!$transaction) {
            Log::warning('pawapay.callback_transaction_not_found', ['deposit_id' => $depositId]);
            return;
        }

        // Idempotence : ne jamais ré-activer une transaction déjà finalisée.
        if (in_array($transaction->status, ['completed', 'refunded', 'cancelled'], true)) {
            Log::info('pawapay.callback_already_finalised', [
                'transaction_id' => $transaction->id,
                'status'         => $transaction->status,
            ]);
            return;
        }

        // Source de vérité : GET /v2/deposits/{depositId}
        $status = $gateway->verifyPayment($depositId);

        PaymentLog::create([
            'transaction_id'    => $transaction->id,
            'gateway'           => 'pawapay',
            'event_type'        => 'deposit.callback',
            'direction'         => 'inbound',
            'payload'           => [
                'verified_status' => $status->status,
                'gateway_status'  => data_get($status->raw, 'data.status'),
                'provider'        => $status->paymentMethod,
            ],
            'gateway_reference' => $depositId,
            'status_code'       => 200,
            'signature_valid'   => true,
            'created_at'        => now(),
        ]);

        $customData    = $transaction->custom_data ?? [];
        $isInstallment = !empty($customData['installment_id']);

        try {
            // Une ÉCHÉANCE se règle seule : elle ne doit jamais tenter d'activer
            // le payable parent avec sa propre transaction (celle-ci n'est pas
            // rattachée à l'investissement). C'est InstallmentService::markPaid()
            // qui active le parent, et seulement au règlement de la dernière.
            if ($isInstallment) {
                $installment = Installment::find($customData['installment_id']);

                if ($status->isPaid()) {
                    if ($installment) {
                        $installments->markPaid($installment, $transaction);
                    }
                    $transaction->update(['status' => 'completed', 'paid_at' => now()]);
                } elseif ($status->status !== PaymentStatus::STATUS_PENDING) {
                    $installment?->update(['status' => 'failed']);
                    $this->markFailed($transaction, $status);
                }

                return;
            }

            match ($transaction->payment_type) {
                'subscription' => $status->isPaid()
                    ? $subscriptions->activate($transaction, $status)
                    : $this->markFailed($transaction, $status),

                'investment' => match (true) {
                    $status->isPaid()                                  => $investments->activate($transaction, $status),
                    $status->status === PaymentStatus::STATUS_REFUNDED => $investments->markRefunded($transaction),
                    default                                            => $this->markFailed($transaction, $status),
                },

                'training' => $status->isPaid()
                    ? $trainings->activate($transaction, $status)
                    : $this->markFailed($transaction, $status),

                default => Log::info('pawapay.callback_type_not_handled', [
                    'type'           => $transaction->payment_type,
                    'transaction_id' => $transaction->id,
                ]),
            };
        } catch (\Throwable $e) {
            Log::error('pawapay.callback_processing_failed', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
            throw $e; // laisse la file retenter
        }
    }

    // ────────────────────── décaissements & remboursements ──────────────────────

    /**
     * Callback de payout (libération d'un jalon d'escrow).
     *
     * EscrowService enregistre le `payoutId` renvoyé par PawaPay dans
     * `transactions.gateway_reference`, et le jalon pointe vers cette
     * transaction via `release_transaction_id`.
     */
    protected function handlePayout(): void
    {
        $payoutId = $this->id('payoutId');
        if (!$payoutId) {
            return;
        }

        $status = strtoupper((string) $this->value('status'));

        $transaction = Transaction::where('gateway_reference', $payoutId)
            ->where('payment_type', 'escrow_release')
            ->first()
            ?? Transaction::where('gateway_reference', $payoutId)->first();

        if (!$transaction) {
            Log::info('pawapay.payout_callback_unmatched', ['payout_id' => $payoutId, 'status' => $status]);
            return;
        }

        $milestone = EscrowMilestone::where('release_transaction_id', $transaction->id)->first();

        if ($status === 'COMPLETED') {
            $transaction->update(['status' => 'completed', 'paid_at' => $transaction->paid_at ?? now()]);
            $milestone?->update(['status' => 'released', 'released_at' => $milestone->released_at ?? now()]);
            return;
        }

        if (in_array($status, ['FAILED', 'REJECTED'], true)) {
            $transaction->update(['status' => 'failed', 'failed_at' => now()]);
            // Le jalon repasse en « approved » : il reste à décaisser.
            $milestone?->update(['status' => 'approved', 'released_at' => null]);

            Log::warning('pawapay.payout_failed', [
                'payout_id'    => $payoutId,
                'milestone_id' => $milestone?->id,
                'reason'       => data_get($this->payload, 'failureReason.failureMessage'),
            ]);
        }
    }

    protected function handleRefund(): void
    {
        $refundId  = $this->id('refundId');
        $depositId = $this->id('depositId');
        $status    = strtoupper((string) $this->value('status'));

        Log::info('pawapay.refund_callback', [
            'refund_id'  => $refundId,
            'deposit_id' => $depositId,
            'status'     => $status,
        ]);

        if ($status !== 'COMPLETED' || !$depositId) {
            return;
        }

        $transaction = $this->resolveTransaction($depositId);
        if ($transaction && $transaction->status !== 'refunded') {
            app(InvestmentService::class)->markRefunded($transaction);
        }
    }

    // ─────────────────────────── helpers ───────────────────────────

    protected function resolveTransaction(string $depositId): ?Transaction
    {
        $transaction = Transaction::where('pawapay_deposit_id', $depositId)
            ->orWhere('paydunya_token', $depositId)
            ->first();

        if ($transaction) {
            return $transaction;
        }

        // Repli sur nos métadonnées (`reference` = id de transaction interne).
        $reference = $this->value('metadata.reference') ?? $this->value('clientReferenceId');

        return $reference ? Transaction::find((int) $reference) : null;
    }

    /** Le payload peut être « nu » ou enveloppé dans `data`. */
    protected function value(string $key): mixed
    {
        return data_get($this->payload, "data.{$key}") ?? data_get($this->payload, $key);
    }

    protected function id(string $key): ?string
    {
        $value = $this->value($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function markFailed(Transaction $transaction, PaymentStatus $status): void
    {
        // ACCEPTED / PROCESSING / IN_RECONCILIATION ne sont PAS des états finaux :
        // on n'échoue la transaction que sur un statut réellement terminal.
        if ($status->status === PaymentStatus::STATUS_PENDING) {
            return;
        }

        if (in_array($transaction->status, ['completed', 'refunded'], true)) {
            return;
        }

        $transaction->update([
            'status'    => $status->status === PaymentStatus::STATUS_CANCELLED ? 'cancelled' : 'failed',
            'failed_at' => now(),
        ]);
    }
}
