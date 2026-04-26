<?php

namespace App\Services\Payment;

use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Investment;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\Training;
use App\Models\TrainingPurchase;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sprint 5 — Paiement fractionné (split payment).
 *
 *  createPlan(user, payable, totalAmount, currency, n, frequency)
 *    -> crée un InstallmentPlan + N Installment lignes (montants équilibrés).
 *  invoiceNext(plan)
 *    -> crée un PayDunya invoice pour la prochaine échéance non payée.
 *  markPaid(installment, transaction)
 *    -> appelé depuis le webhook quand l'installment est réglé.
 *  processDueSweep()
 *    -> daily cron : génère les invoices PayDunya pour toutes les échéances dues.
 */
class InstallmentService
{
    public function __construct(
        protected PaymentGatewayFactory $gatewayFactory,
        protected CurrencyService       $currency,
    ) {}

    /**
     * Crée un plan de N échéances (montants pondérés en cents pour éviter les arrondis perdus).
     */
    public function createPlan(
        User $user,
        $payable,
        float $totalAmount,
        string $currency,
        int $totalInstallments,
        string $frequency = 'monthly',
        ?Carbon $startsAt = null,
    ): InstallmentPlan {
        if ($totalInstallments < 2 || $totalInstallments > 12) {
            throw new RuntimeException('Le nombre d\'échéances doit être compris entre 2 et 12.');
        }

        $paymentType = match (true) {
            $payable instanceof Investment       => 'investment',
            $payable instanceof Subscription     => 'subscription',
            $payable instanceof TrainingPurchase => 'training',
            default => throw new RuntimeException('Type de payable non supporté pour le fractionnement.'),
        };

        $startsAt ??= now();

        return DB::transaction(function () use (
            $user, $payable, $totalAmount, $currency, $totalInstallments, $frequency, $startsAt, $paymentType
        ) {
            $plan = InstallmentPlan::create([
                'user_id'            => $user->id,
                'payable_type'       => get_class($payable),
                'payable_id'         => $payable->getKey(),
                'payment_type'       => $paymentType,
                'total_amount'       => $this->currency->round($totalAmount, $currency),
                'currency'           => strtoupper($currency),
                'total_installments' => $totalInstallments,
                'frequency'          => $frequency,
                'starts_at'          => $startsAt,
                'next_due_at'        => $startsAt,
                'status'             => 'active',
            ]);

            // Splits égaux, le dernier absorbe le reste pour ne pas perdre de centimes.
            $share = (float) round($totalAmount / $totalInstallments, 2);
            $running = 0.0;
            for ($i = 1; $i <= $totalInstallments; $i++) {
                $amount = $i === $totalInstallments
                    ? round($totalAmount - $running, 2)
                    : $share;
                $running += $amount;

                Installment::create([
                    'installment_plan_id' => $plan->id,
                    'number'              => $i,
                    'amount'              => $this->currency->round($amount, $currency),
                    'currency'            => strtoupper($currency),
                    'status'              => 'pending',
                    'due_date'            => $this->dueDateFor($startsAt, $frequency, $i - 1),
                ]);
            }

            return $plan->fresh('installments');
        });
    }

    protected function dueDateFor(Carbon $start, string $frequency, int $index): Carbon
    {
        $start = $start->copy();
        return match ($frequency) {
            'weekly'    => $start->addWeeks($index),
            'biweekly'  => $start->addWeeks($index * 2),
            'monthly'   => $start->addMonthsNoOverflow($index),
            default     => $start->addMonthsNoOverflow($index),
        };
    }

    /**
     * Génère un invoice PayDunya pour la prochaine échéance non payée du plan.
     * Renvoie l'URL d'invoice à présenter à l'utilisateur.
     */
    public function invoiceNext(InstallmentPlan $plan): array
    {
        $installment = $plan->installments()
            ->whereIn('status', ['pending', 'failed'])
            ->orderBy('number')
            ->first();

        if (!$installment) {
            throw new RuntimeException('Aucune échéance en attente sur ce plan.');
        }

        $user = $plan->user;
        if (!$user) {
            throw new RuntimeException('Utilisateur introuvable pour ce plan.');
        }

        $gateway = $this->gatewayFactory->forCountry($user->country ?? 'SN');

        // Convertir en XOF si nécessaire (PayDunya).
        $amount = (float) $installment->amount;
        $charged = $installment->currency === 'XOF'
            ? $amount
            : $this->currency->round(
                $this->currency->convert($amount, $installment->currency, 'XOF'),
                'XOF',
            );
        $chargedCurrency = 'XOF';

        if ($charged < 200) $charged = 200; // sandbox minimum

        $description = "Échéance {$installment->number}/{$plan->total_installments} — plan #{$plan->id}";

        $transaction = DB::transaction(function () use ($user, $plan, $installment, $charged, $chargedCurrency, $gateway, $description) {
            $tx = Transaction::create([
                'user_id'           => $user->id,
                'transactable_type' => $plan->payable_type,
                'transactable_id'   => $plan->payable_id,
                'amount'            => $charged,
                'currency'          => $chargedCurrency,
                'gateway'           => $gateway->getName(),
                'gateway_reference' => 'ins_' . Str::random(20),
                'payment_type'      => $plan->payment_type,
                'status'            => 'pending',
                'installment_number' => $installment->number,
                'installment_total'  => $plan->total_installments,
                'customer_name'     => $user->name,
                'customer_email'    => $user->email,
                'customer_phone'    => $user->phone ?? null,
                'description'       => $description,
                'custom_data'       => [
                    'installment_plan_id' => $plan->id,
                    'installment_id'      => $installment->id,
                    'installment_number'  => $installment->number,
                    'installment_total'   => $plan->total_installments,
                    'native_amount'       => (float) $installment->amount,
                    'native_currency'     => $installment->currency,
                ],
            ]);

            $installment->update([
                'status'         => 'invoiced',
                'transaction_id' => $tx->id,
            ]);

            return $tx;
        });

        $checkout = $gateway->createCheckout([
            'amount'       => $charged,
            'currency'     => $chargedCurrency,
            'description'  => $description,
            'item_name'    => $description,
            'reference'    => (string) $transaction->id,
            'payment_type' => $plan->payment_type,
            'customer'     => [
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
            ],
            'custom_data'  => [
                'transaction_id'      => $transaction->id,
                'installment_plan_id' => $plan->id,
                'installment_id'      => $installment->id,
                'user_id'             => $user->id,
                'payment_type'        => $plan->payment_type,
            ],
        ]);

        if (!$checkout->success) {
            DB::transaction(function () use ($transaction, $installment) {
                $transaction->update(['status' => 'failed', 'failed_at' => now()]);
                $installment->update(['status' => 'failed']);
            });
            throw new RuntimeException($checkout->message ?: 'Création de l\'invoice échouée.');
        }

        $transaction->update([
            'paydunya_token'       => $checkout->token,
            'paydunya_invoice_url' => $checkout->invoiceUrl,
            'status'               => 'processing',
        ]);

        return [
            'installment'  => $installment->fresh(),
            'transaction'  => $transaction,
            'checkout'     => [
                'token'   => $checkout->token,
                'url'     => $checkout->invoiceUrl,
                'gateway' => $gateway->getName(),
            ],
        ];
    }

    /**
     * Marque une installment comme payée et avance le plan.
     * Appelée depuis ProcessPayDunyaWebhook quand custom_data.installment_id est présent.
     */
    public function markPaid(Installment $installment, Transaction $transaction): InstallmentPlan
    {
        return DB::transaction(function () use ($installment, $transaction) {
            if ($installment->status === 'paid') {
                return $installment->plan;
            }

            $installment->update([
                'status'         => 'paid',
                'paid_at'        => now(),
                'transaction_id' => $transaction->id,
            ]);

            $plan = $installment->plan;
            $plan->increment('paid_installments');
            $plan->refresh();

            $next = $plan->installments()
                ->where('status', '!=', 'paid')
                ->orderBy('number')
                ->first();

            if ($next) {
                $plan->update(['next_due_at' => $next->due_date]);
            } else {
                $plan->update([
                    'status'       => 'completed',
                    'next_due_at'  => null,
                    'completed_at' => now(),
                ]);
            }

            return $plan;
        });
    }

    /**
     * Cron quotidien : pour chaque plan actif dont la prochaine échéance est due,
     * tente de générer un invoice et notifie l'utilisateur (TODO email).
     */
    public function processDueSweep(): array
    {
        $plans = InstallmentPlan::query()
            ->active()
            ->where('next_due_at', '<=', now())
            ->get();

        $invoiced = 0;
        $failed = 0;

        foreach ($plans as $plan) {
            try {
                $this->invoiceNext($plan);
                $invoiced++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Installment invoicing failed', [
                    'plan_id' => $plan->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return ['scanned' => $plans->count(), 'invoiced' => $invoiced, 'failed' => $failed];
    }
}
