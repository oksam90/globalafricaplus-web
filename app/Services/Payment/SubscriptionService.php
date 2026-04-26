<?php

namespace App\Services\Payment;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payment\DTOs\CheckoutResult;
use App\Services\Payment\DTOs\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Business-logic service orchestrating subscription lifecycle:
 *  1. initiate()     — create a pending Transaction + PayDunya invoice URL
 *  2. activate()     — on successful webhook, flip Transaction + create/renew Subscription
 *  3. cancel()       — user-requested cancellation (keeps access until ends_at)
 *  4. markRefunded() — on refund webhook / admin action within 30-day guarantee
 */
class SubscriptionService
{
    public function __construct(
        protected PaymentGatewayFactory $gatewayFactory,
    ) {}

    /**
     * Start a new subscription payment flow.
     * Returns the CheckoutResult so the frontend can redirect to the hosted invoice.
     *
     * @param array{plan_slug:string, billing_cycle:string, country?:string, channel?:string} $data
     */
    public function initiate(User $user, array $data): array
    {
        $plan = SubscriptionPlan::where('slug', $data['plan_slug'])->firstOrFail();
        $cycle = $data['billing_cycle'] ?? 'monthly';
        $country = $this->normalizeCountry($data['country'] ?? $user->country ?? 'SN');

        if ($plan->isFree()) {
            // Free plan: activate instantly, no checkout needed.
            $subscription = $this->createOrRenewSubscription(
                user: $user,
                plan: $plan,
                cycle: $cycle,
                amount: 0,
                currency: $plan->currency ?? 'XOF',
                gateway: null,
                gatewayRef: null,
                immediatelyActive: true,
            );
            return [
                'status'       => 'activated',
                'subscription' => $subscription->load('plan'),
                'checkout'     => null,
            ];
        }

        $priceRaw     = $cycle === 'yearly' ? (float) $plan->price_yearly : (float) $plan->price_monthly;
        $planCurrency = $plan->currency ?: 'XOF';

        // Gateway routing (UEMOA/CEMAC → PayDunya).
        $gateway = $this->gatewayFactory->forCountry($country);

        // PayDunya only accepts XOF (FCFA) — convert EUR/USD prices if needed.
        if ($gateway->getName() === 'paydunya' && strtoupper($planCurrency) !== 'XOF') {
            $rate  = $gateway->getExchangeRate($planCurrency, 'XOF');
            $price = (float) round($priceRaw * $rate, 0); // whole FCFA
            $currency = 'XOF';
        } else {
            $price    = $priceRaw;
            $currency = $planCurrency;
        }

        // PayDunya sandbox minimum is 200 FCFA.
        if ($gateway->getName() === 'paydunya' && $currency === 'XOF' && $price < 200) {
            $price = 200;
        }

        // Create the pending Transaction first so we can pass its id as reference.
        $transaction = DB::transaction(function () use ($user, $plan, $price, $currency, $cycle, $gateway) {
            return Transaction::create([
                'user_id'        => $user->id,
                'transactable_type' => SubscriptionPlan::class,
                'transactable_id'   => $plan->id,
                'amount'         => $price,
                'currency'       => $currency,
                'gateway'        => $gateway->getName(),
                'gateway_reference' => 'sub_' . Str::random(20),
                'payment_type'   => 'subscription',
                'status'         => 'pending',
                'customer_name'  => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $user->phone ?? null,
                'customer_country' => $this->normalizeCountry($user->country ?? 'SN'),
                'description'    => "Abonnement {$plan->name} ({$cycle})",
                'custom_data'    => [
                    'plan_slug'     => $plan->slug,
                    'billing_cycle' => $cycle,
                ],
                'ip_address'     => request()?->ip(),
                'user_agent'     => substr((string) request()?->userAgent(), 0, 500),
            ]);
        });

        $checkout = $gateway->createCheckout([
            'amount'       => $price,
            'currency'     => $currency,
            'description'  => "Abonnement {$plan->name} — " . ($cycle === 'yearly' ? 'annuel' : 'mensuel'),
            'item_name'    => "Globalafrica+ — {$plan->name}",
            'reference'    => (string) $transaction->id,
            'payment_type' => 'subscription',
            'channel'      => $data['channel'] ?? null,
            'customer'     => [
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
            ],
            'custom_data'  => [
                'transaction_id' => $transaction->id,
                'user_id'        => $user->id,
                'plan'           => $plan->slug,
                'billing_cycle'  => $cycle,
            ],
        ]);

        if (!$checkout->success) {
            $transaction->update([
                'status'     => 'failed',
                'failed_at'  => now(),
            ]);
            throw new RuntimeException($checkout->message ?: 'Création du paiement impossible.');
        }

        $transaction->update([
            'paydunya_token'       => $checkout->token,
            'paydunya_invoice_url' => $checkout->invoiceUrl,
            'status'               => 'processing',
        ]);

        return [
            'status'        => 'checkout_required',
            'transaction'   => $transaction,
            'checkout'      => [
                'token'   => $checkout->token,
                'url'     => $checkout->invoiceUrl,
                'gateway' => $gateway->getName(),
            ],
        ];
    }

    /**
     * Activate a subscription after a successful payment is confirmed.
     *
     * Idempotent: safe to call multiple times for the same transaction
     * (PayDunya webhooks can be delivered more than once).
     */
    public function activate(Transaction $transaction, PaymentStatus $status): Subscription
    {
        if (!$status->isPaid()) {
            throw new RuntimeException("Cannot activate subscription: payment status is {$status->status}.");
        }

        return DB::transaction(function () use ($transaction, $status) {
            $user = $transaction->user;
            $customData = $transaction->custom_data ?? [];
            $planSlug = $customData['plan_slug'] ?? null;
            $cycle    = $customData['billing_cycle'] ?? 'monthly';

            if (!$planSlug) {
                throw new RuntimeException('Transaction missing plan_slug in custom_data.');
            }

            $plan = SubscriptionPlan::where('slug', $planSlug)->firstOrFail();

            // Mark transaction as paid (idempotent).
            if ($transaction->status !== 'completed') {
                $transaction->update([
                    'status'              => 'completed',
                    'paid_at'             => now(),
                    'paydunya_receipt_url' => $status->receiptUrl,
                    'paydunya_channel'    => $status->paymentMethod,
                ]);
            }

            // Idempotency guard — if a subscription was already created from this transaction, return it.
            $existing = Subscription::where('payment_reference', (string) $transaction->id)->first();
            if ($existing) {
                return $existing;
            }

            return $this->createOrRenewSubscription(
                user: $user,
                plan: $plan,
                cycle: $cycle,
                amount: (float) $transaction->amount,
                currency: $transaction->currency,
                gateway: $transaction->gateway,
                gatewayRef: $transaction->paydunya_token,
                immediatelyActive: true,
                paymentReference: (string) $transaction->id,
            );
        });
    }

    /**
     * Cancel the current active subscription (keeps access until ends_at).
     */
    public function cancel(User $user): Subscription
    {
        $sub = $user->activeSubscription();
        if (!$sub || ($sub->plan && $sub->plan->isFree())) {
            throw new RuntimeException('Aucun abonnement payant à annuler.');
        }

        $sub->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return $sub;
    }

    /**
     * Mark a subscription as refunded (30-day guarantee).
     */
    public function markRefunded(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $transaction->update([
                'status'      => 'refunded',
                'refunded_at' => now(),
            ]);

            $sub = Subscription::where('payment_reference', (string) $transaction->id)->first();
            if ($sub) {
                $sub->update([
                    'status'      => 'refunded',
                    'refunded_at' => now(),
                ]);
            }
        });
    }

    /**
     * Normalise a country value into an ISO-3166 alpha-2 code.
     * Accepts already-valid 2-letter codes, common French/English country
     * names used in the users table, falls back to 'SN' for unknowns.
     */
    protected function normalizeCountry(?string $raw): string
    {
        if (!$raw) return 'SN';
        $s = trim($raw);
        if (strlen($s) === 2) return strtoupper($s);

        $key = strtolower(str_replace(
            ['é','è','ê','à','ï','ô','û','ç','-',"'",'`'],
            ['e','e','e','a','i','o','u','c',' ',' ',' '],
            $s,
        ));
        $map = [
            'senegal'        => 'SN',
            'cote d ivoire'  => 'CI',
            'ivory coast'    => 'CI',
            'mali'           => 'ML',
            'burkina faso'   => 'BF',
            'togo'           => 'TG',
            'benin'          => 'BJ',
            'niger'          => 'NE',
            'guinee bissau'  => 'GW',
            'cameroun'       => 'CM',
            'cameroon'       => 'CM',
            'gabon'          => 'GA',
            'congo'          => 'CG',
            'tchad'          => 'TD',
            'chad'           => 'TD',
            'nigeria'        => 'NG',
            'ghana'          => 'GH',
            'kenya'          => 'KE',
            'france'         => 'FR',
            'belgique'       => 'BE',
            'suisse'         => 'CH',
            'canada'         => 'CA',
            'etats unis'     => 'US',
            'united states'  => 'US',
        ];
        return $map[$key] ?? 'SN';
    }

    /**
     * Shared subscription creation/renewal helper.
     */
    protected function createOrRenewSubscription(
        User $user,
        SubscriptionPlan $plan,
        string $cycle,
        float $amount,
        string $currency,
        ?string $gateway,
        ?string $gatewayRef,
        bool $immediatelyActive,
        ?string $paymentReference = null,
    ): Subscription {
        // Cancel any still-active subscription before opening the new one.
        Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'trialing'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $endsAt = match ($cycle) {
            'yearly'  => now()->addYear(),
            default   => now()->addMonth(),
        };

        $planColumn = in_array($plan->slug, ['starter', 'pro', 'enterprise'], true) ? $plan->slug : 'starter';

        return Subscription::create([
            'user_id'                  => $user->id,
            'plan_id'                  => $plan->id,
            'plan_slug'                => $planColumn,
            'billing_cycle'            => $cycle,
            'amount'                   => $amount,
            'currency'                 => $currency,
            'status'                   => $immediatelyActive ? 'active' : 'trialing',
            'starts_at'                => now(),
            'ends_at'                  => $plan->isFree() ? null : $endsAt,
            'trial_ends_at'            => $plan->isFree() ? null : now()->addDays(30),
            'payment_method'           => $gateway,
            'payment_gateway'          => $gateway,
            'gateway_subscription_ref' => $gatewayRef,
            'payment_reference'        => $paymentReference,
        ]);
    }
}
