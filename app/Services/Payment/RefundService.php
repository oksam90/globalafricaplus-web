<?php

namespace App\Services\Payment;

use App\Models\PaymentLog;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\TrainingPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sprint 5 — Garantie « satisfait ou remboursé » 30 jours.
 *
 * Couvre les abonnements (Subscription) et les achats de formation
 * (TrainingPurchase). Les remboursements sont effectués via PayDunya
 * DirectPay vers le téléphone enregistré sur la transaction d'origine
 * (même mécanisme que le remboursement escrow Sprint 4, mais déclenché
 * par l'utilisateur dans la fenêtre 30j).
 */
class RefundService
{
    public function __construct(
        protected PaymentGatewayFactory $gatewayFactory,
        protected CurrencyService       $currency,
    ) {}

    /**
     * Rembourse un abonnement éligible (annulé dans les 30 jours).
     */
    public function refundSubscription(Subscription $sub, User $requester): Subscription
    {
        if ($sub->user_id !== $requester->id) {
            throw new RuntimeException("Vous n'êtes pas le titulaire de cet abonnement.");
        }
        if (!$sub->isRefundable()) {
            throw new RuntimeException('La fenêtre de garantie de 30 jours est dépassée.');
        }
        if ($sub->status === 'refunded') {
            return $sub;
        }

        $originalTx = Transaction::where('user_id', $sub->user_id)
            ->where('payment_type', 'subscription')
            ->where('status', 'completed')
            ->latest('paid_at')
            ->first();

        if (!$originalTx) {
            throw new RuntimeException("Transaction d'abonnement introuvable.");
        }

        $this->dispatchRefund(
            $originalTx,
            $sub,
            "Remboursement abonnement {$sub->plan_slug} — garantie 30j",
            ['subscription_id' => $sub->id, 'reason' => 'guarantee_30d_subscription'],
        );

        $sub->update([
            'status'      => 'refunded',
            'cancelled_at' => $sub->cancelled_at ?: now(),
            'refunded_at' => now(),
        ]);

        return $sub->fresh();
    }

    /**
     * Rembourse un achat de formation éligible.
     */
    public function refundTrainingPurchase(TrainingPurchase $purchase, User $requester): TrainingPurchase
    {
        if ($purchase->user_id !== $requester->id) {
            throw new RuntimeException("Vous n'êtes pas l'acheteur de cette formation.");
        }
        if (!$purchase->isRefundable()) {
            throw new RuntimeException('La fenêtre de garantie de 30 jours est dépassée.');
        }
        if ($purchase->status === 'refunded') {
            return $purchase;
        }

        $originalTx = $purchase->transaction;
        if (!$originalTx) {
            throw new RuntimeException("Transaction d'origine introuvable.");
        }

        $this->dispatchRefund(
            $originalTx,
            $purchase,
            "Remboursement formation #{$purchase->training_id} — garantie 30j",
            ['training_purchase_id' => $purchase->id, 'reason' => 'guarantee_30d_training'],
        );

        $purchase->update([
            'status'            => 'refunded',
            'refunded_at'       => now(),
            'access_revoked_at' => now(),
        ]);

        return $purchase->fresh();
    }

    /**
     * Crée la transaction de remboursement et appelle PayDunya DirectPay.
     */
    protected function dispatchRefund(
        Transaction $originalTx,
        $payable,
        string $description,
        array $customData,
    ): Transaction {
        $phone = $originalTx->customer_phone ?: optional($originalTx->user)->phone;
        if (empty($phone)) {
            throw new RuntimeException("Numéro de téléphone indisponible pour le remboursement.");
        }

        $gateway = $this->gatewayFactory->make('paydunya');

        // Convertir en XOF si la transaction d'origine n'était pas déjà en XOF.
        $amountXof = $this->currency->round(
            $this->currency->convert(
                (float) $originalTx->amount,
                (string) $originalTx->currency,
                'XOF',
            ),
            'XOF',
        );

        $refundTx = DB::transaction(function () use ($originalTx, $amountXof, $gateway, $phone, $description, $customData) {
            return Transaction::create([
                'user_id'           => $originalTx->user_id,
                'transactable_type' => $originalTx->transactable_type,
                'transactable_id'   => $originalTx->transactable_id,
                'amount'            => $amountXof,
                'currency'          => 'XOF',
                'gateway'           => $gateway->getName(),
                'gateway_reference' => 'rfg_' . Str::random(20),
                'payment_type'      => 'refund',
                'status'            => 'processing',
                'customer_phone'    => $phone,
                'customer_country'  => $originalTx->customer_country,
                'customer_name'     => $originalTx->customer_name,
                'customer_email'    => $originalTx->customer_email,
                'description'       => $description,
                'custom_data'       => array_merge(
                    ['original_transaction_id' => $originalTx->id],
                    $customData,
                ),
            ]);
        });

        $result = $gateway->disburse($phone, $amountXof, 'paydunya');

        try {
            PaymentLog::create([
                'transaction_id'    => $refundTx->id,
                'gateway'           => 'paydunya',
                'event_type'        => $result->success ? 'refund.success' : 'refund.failed',
                'direction'         => 'outgoing',
                'gateway_reference' => $result->disburseReference,
                'payload'           => array_merge($result->raw, ['message' => $result->message]),
                'created_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Refund PaymentLog write failed', ['message' => $e->getMessage()]);
        }

        if (!$result->success) {
            $refundTx->update(['status' => 'failed', 'failed_at' => now()]);
            throw new RuntimeException($result->message ?: 'Échec du remboursement PayDunya.');
        }

        $refundTx->update([
            'status'            => 'completed',
            'paid_at'           => now(),
            'gateway_reference' => $result->disburseReference ?: $refundTx->gateway_reference,
        ]);

        $originalTx->update([
            'status'      => 'refunded',
            'refunded_at' => now(),
        ]);

        return $refundTx;
    }
}
