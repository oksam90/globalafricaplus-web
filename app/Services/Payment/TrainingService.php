<?php

namespace App\Services\Payment;

use App\Models\Training;
use App\Models\TrainingPurchase;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payment\DTOs\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sprint 5 — Achat & accès aux formations.
 *
 *  initiate(user, training, options)
 *    -> crée Transaction + TrainingPurchase pending, renvoie checkout PayDunya
 *  activate(transaction, status)
 *    -> sur IPN positif : passe à active, fixe refund_deadline (paid_at + 30j),
 *       incrémente Training.purchases_count
 *  hasAccess(user, training)
 *    -> contrôle d'accès simple pour servir le contenu débloqué
 */
class TrainingService
{
    public function __construct(
        protected PaymentGatewayFactory $gatewayFactory,
        protected CurrencyService       $currency,
        protected FeeCalculator         $fees,
    ) {}

    public function initiate(User $user, Training $training, array $opts = []): array
    {
        if (!$training->is_published) {
            throw new RuntimeException('Cette formation n\'est pas disponible à l\'achat.');
        }

        $existing = TrainingPurchase::where('user_id', $user->id)
            ->where('training_id', $training->id)
            ->whereIn('status', ['active', 'pending'])
            ->first();

        if ($existing && $existing->status === 'active') {
            throw new RuntimeException('Vous avez déjà acheté cette formation.');
        }

        // `transactions.customer_country` est un char(2) : un nom de pays non
        // normalisé (« Sénégal ») y était tronqué en octets invalides et
        // provoquait un SQLSTATE[22001] au moment de l'insertion.
        $country      = $this->fees->normalizeCountry($opts['country'] ?? $user->country ?? null);
        $baseCurrency = strtoupper($training->currency ?: 'EUR');

        // Moyen de paiement choisi : mobile_money (PawaPay) | card (PayDunya)
        $method  = $opts['payment_method'] ?? null;
        $gateway = $this->gatewayFactory->forMethod($method);

        $price = (float) $training->price;

        // Devise réellement débitée : FCFA pour PayDunya, devise du marché
        // mobile money du pays de l'acheteur pour PawaPay.
        $chargedCurrency = $gateway->getName() === 'pawapay'
            ? $this->fees->marketCurrency($country)
            : 'XOF';

        $charged = $chargedCurrency === $baseCurrency
            ? $price
            : $this->currency->round(
                $this->currency->convert($price, $baseCurrency, $chargedCurrency),
                $chargedCurrency,
            );

        if ($gateway->getName() === 'paydunya' && $charged < 200) {
            $charged = 200; // minimum PayDunya
        }

        [$purchase, $transaction] = DB::transaction(function () use (
            $user, $training, $price, $baseCurrency, $charged, $chargedCurrency, $gateway, $country, $existing
        ) {
            $transaction = Transaction::create([
                'user_id'           => $user->id,
                'transactable_type' => Training::class,
                'transactable_id'   => $training->id,
                'amount'            => $charged,
                'currency'          => $chargedCurrency,
                'gateway'           => $gateway->getName(),
                'gateway_reference' => 'trn_' . Str::random(20),
                'payment_type'      => 'training',
                'status'            => 'pending',
                'customer_name'     => $user->name,
                'customer_email'    => $user->email,
                'customer_phone'    => $user->phone ?? null,
                'customer_country'  => $country,
                'description'       => "Achat formation — {$training->title}",
                'custom_data'       => [
                    'training_id'      => $training->id,
                    'training_slug'    => $training->slug,
                    'native_amount'    => $price,
                    'native_currency'  => $baseCurrency,
                ],
            ]);

            $purchase = $existing
                ?: TrainingPurchase::create([
                    'user_id'     => $user->id,
                    'training_id' => $training->id,
                    'amount'      => $price,
                    'currency'    => $baseCurrency,
                    'status'      => 'pending',
                ]);

            $purchase->update(['transaction_id' => $transaction->id]);

            return [$purchase, $transaction];
        });

        $checkout = $gateway->createCheckout([
            'amount'       => $charged,
            'currency'     => $chargedCurrency,
            'description'  => "Formation : {$training->title}",
            'item_name'    => $training->title,
            'reference'    => (string) $transaction->id,
            'payment_type' => 'training',
            'channel'      => $opts['channel'] ?? ($method === 'card' ? 'card' : null),
            'country'      => $country,
            'customer'     => [
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
            ],
            'custom_data'  => [
                'transaction_id' => $transaction->id,
                'training_id'    => $training->id,
                'user_id'        => $user->id,
                'payment_type'   => 'training',
            ],
        ]);

        if (!$checkout->success) {
            DB::transaction(function () use ($transaction, $purchase) {
                $transaction->update(['status' => 'failed', 'failed_at' => now()]);
                $purchase->update(['status' => 'failed']);
            });
            throw new RuntimeException($checkout->message ?: 'Création du paiement impossible.');
        }

        $transaction->update(array_filter([
            'paydunya_token'       => $checkout->token,
            'paydunya_invoice_url' => $checkout->invoiceUrl,
            'pawapay_deposit_id'   => $gateway->getName() === 'pawapay' ? $checkout->token : null,
            'pawapay_checkout_url' => $gateway->getName() === 'pawapay' ? $checkout->invoiceUrl : null,
            'status'               => 'processing',
        ]));

        return [
            'status'      => 'checkout_required',
            'purchase'    => $purchase,
            'transaction' => $transaction,
            'checkout'    => [
                'token'   => $checkout->token,
                'url'     => $checkout->invoiceUrl,
                'gateway' => $gateway->getName(),
            ],
        ];
    }

    public function activate(Transaction $transaction, PaymentStatus $status): TrainingPurchase
    {
        if (!$status->isPaid()) {
            throw new RuntimeException("Cannot activate training: status is {$status->status}.");
        }

        return DB::transaction(function () use ($transaction, $status) {
            $purchase = TrainingPurchase::where('transaction_id', $transaction->id)->first();
            if (!$purchase) {
                $cd = $transaction->custom_data ?? [];
                if (!empty($cd['training_id'])) {
                    $purchase = TrainingPurchase::where('user_id', $transaction->user_id)
                        ->where('training_id', $cd['training_id'])
                        ->latest()->first();
                }
            }
            if (!$purchase) {
                throw new RuntimeException("TrainingPurchase not found for transaction #{$transaction->id}.");
            }
            if ($purchase->status === 'active') {
                return $purchase;
            }

            $now = now();
            $purchase->update([
                'status'          => 'active',
                'paid_at'         => $now,
                'refund_deadline' => $now->copy()->addDays(30),
            ]);

            if ($transaction->status !== 'completed') {
                $transaction->update([
                    'status'               => 'completed',
                    'paid_at'              => $now,
                    'paydunya_receipt_url' => $status->receiptUrl,
                    'paydunya_channel'     => $status->paymentMethod,
                ]);
            }

            $purchase->training()->increment('purchases_count');

            return $purchase->fresh();
        });
    }

    public function hasAccess(User $user, Training $training): bool
    {
        return TrainingPurchase::where('user_id', $user->id)
            ->where('training_id', $training->id)
            ->where('status', 'active')
            ->whereNull('access_revoked_at')
            ->exists();
    }
}
