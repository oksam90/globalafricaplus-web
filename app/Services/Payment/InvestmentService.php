<?php

namespace App\Services\Payment;

use App\Models\EscrowMilestone;
use App\Models\Investment;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payment\DTOs\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrates the investment lifecycle:
 *  1. initiate()     — create a pending Investment + Transaction, return PayDunya checkout URL
 *  2. activate()     — on successful IPN, flip Investment to "escrow", bump Project.amount_raised,
 *                      and seed default milestones if none exist yet.
 *  3. markRefunded() — on refund: roll back the amount_raised, flag Investment as refunded.
 */
class InvestmentService
{
    public function __construct(
        protected PaymentGatewayFactory $gatewayFactory,
    ) {}

    /**
     * Start a new investment payment flow.
     *
     * @param array{project_id:int, amount:float, type?:string, country?:string, channel?:string} $data
     */
    public function initiate(User $user, array $data): array
    {
        $project = Project::where('status', 'published')
            ->findOrFail($data['project_id']);

        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new RuntimeException('Le montant d\'investissement doit être supérieur à zéro.');
        }

        $type = $data['type'] ?? 'equity';
        if (!in_array($type, ['equity', 'donation', 'loan', 'reward'], true)) {
            throw new RuntimeException('Type d\'investissement invalide.');
        }

        $country = $this->normalizeCountry($data['country'] ?? $user->country ?? 'SN');
        $baseCurrency = strtoupper($project->currency ?: 'EUR');

        $gateway = $this->gatewayFactory->forCountry($country);

        // PayDunya only handles XOF — convert if the project is priced in EUR/USD.
        if ($gateway->getName() === 'paydunya' && $baseCurrency !== 'XOF') {
            $rate     = $gateway->getExchangeRate($baseCurrency, 'XOF');
            $charged  = (float) round($amount * $rate, 0);
            $chargedCurrency = 'XOF';
        } else {
            $charged  = $amount;
            $chargedCurrency = $baseCurrency;
        }

        // Sandbox minimum
        if ($gateway->getName() === 'paydunya' && $chargedCurrency === 'XOF' && $charged < 200) {
            $charged = 200;
        }

        [$investment, $transaction] = DB::transaction(function () use (
            $user, $project, $amount, $baseCurrency, $charged, $chargedCurrency, $type, $gateway, $country
        ) {
            $transaction = Transaction::create([
                'user_id'           => $user->id,
                'transactable_type' => Project::class,
                'transactable_id'   => $project->id,
                'amount'            => $charged,
                'currency'          => $chargedCurrency,
                'gateway'           => $gateway->getName(),
                'gateway_reference' => 'inv_' . Str::random(20),
                'payment_type'      => 'investment',
                'status'            => 'pending',
                'customer_name'     => $user->name,
                'customer_email'    => $user->email,
                'customer_phone'    => $user->phone ?? null,
                'customer_country'  => $country,
                'description'      => "Investissement — {$project->title}",
                'custom_data'      => [
                    'project_id'       => $project->id,
                    'project_slug'     => $project->slug,
                    'investment_type'  => $type,
                    'native_amount'    => $amount,
                    'native_currency'  => $baseCurrency,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            ]);

            $investment = Investment::create([
                'project_id'         => $project->id,
                'investor_id'        => $user->id,
                'amount'             => $amount,
                'currency'           => $baseCurrency,
                'charged_amount'     => $charged,
                'charged_currency'   => $chargedCurrency,
                'type'               => $type,
                'status'             => 'pending',
                'payment_provider'   => $gateway->getName(),
                'provider_reference' => $transaction->gateway_reference,
                'transaction_id'     => $transaction->id,
            ]);

            return [$investment, $transaction];
        });

        $checkout = $gateway->createCheckout([
            'amount'       => $charged,
            'currency'     => $chargedCurrency,
            'description'  => "Investissement dans « {$project->title} »",
            'item_name'    => "Projet {$project->title}",
            'reference'    => (string) $transaction->id,
            'payment_type' => 'investment',
            'channel'      => $data['channel'] ?? null,
            'customer'     => [
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
            ],
            'custom_data'  => [
                'transaction_id'   => $transaction->id,
                'investment_id'    => $investment->id,
                'project_id'       => $project->id,
                'user_id'          => $user->id,
                'payment_type'     => 'investment',
            ],
        ]);

        if (!$checkout->success) {
            DB::transaction(function () use ($transaction, $investment) {
                $transaction->update(['status' => 'failed', 'failed_at' => now()]);
                $investment->update(['status' => 'failed']);
            });
            throw new RuntimeException($checkout->message ?: 'Création du paiement impossible.');
        }

        $transaction->update([
            'paydunya_token'       => $checkout->token,
            'paydunya_invoice_url' => $checkout->invoiceUrl,
            'status'               => 'processing',
        ]);

        $investment->update([
            'paydunya_token' => $checkout->token,
        ]);

        return [
            'status'      => 'checkout_required',
            'investment'  => $investment,
            'transaction' => $transaction,
            'checkout'    => [
                'token'   => $checkout->token,
                'url'     => $checkout->invoiceUrl,
                'gateway' => $gateway->getName(),
            ],
        ];
    }

    /**
     * Activate an investment after the gateway confirms payment.
     * Idempotent.
     */
    public function activate(Transaction $transaction, PaymentStatus $status): Investment
    {
        if (!$status->isPaid()) {
            throw new RuntimeException("Cannot activate investment: payment status is {$status->status}.");
        }

        return DB::transaction(function () use ($transaction, $status) {
            $investment = Investment::where('transaction_id', $transaction->id)->first();

            if (!$investment) {
                $customData = $transaction->custom_data ?? [];
                if (!empty($customData['investment_id'])) {
                    $investment = Investment::find($customData['investment_id']);
                }
            }

            if (!$investment) {
                throw new RuntimeException("Investment not found for transaction #{$transaction->id}.");
            }

            // Idempotency: if already paid, return as-is.
            if (in_array($investment->status, ['escrow', 'released'], true)) {
                return $investment;
            }

            if ($transaction->status !== 'completed') {
                $transaction->update([
                    'status'               => 'completed',
                    'paid_at'              => now(),
                    'paydunya_receipt_url' => $status->receiptUrl,
                    'paydunya_channel'     => $status->paymentMethod,
                ]);
            }

            $investment->update([
                'status'               => 'escrow',
                'paid_at'              => now(),
                'paydunya_receipt_url' => $status->receiptUrl,
                'paydunya_channel'     => $status->paymentMethod,
            ]);

            // Bump the project's amount_raised using the NATIVE amount (EUR/USD),
            // not the XOF charged amount, so the progress bar stays consistent
            // with amount_needed which is also expressed in the project currency.
            $project = $investment->project;
            if ($project) {
                $project->increment('amount_raised', (float) $investment->amount);
            }

            // Seed default milestones on the FIRST successful investment only.
            if ($project && $project->escrowMilestones()->count() === 0) {
                $this->seedDefaultMilestones($project, $investment);
            }

            return $investment->fresh();
        });
    }

    /**
     * Roll back a refunded investment.
     */
    public function markRefunded(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $investment = Investment::where('transaction_id', $transaction->id)->first();

            if (!$investment || $investment->status === 'refunded') {
                return;
            }

            // Only deduct from amount_raised if we actually credited it.
            if (in_array($investment->status, ['escrow', 'released'], true) && $investment->project) {
                $investment->project->decrement('amount_raised', (float) $investment->amount);
            }

            $investment->update([
                'status'      => 'refunded',
                'refunded_at' => now(),
            ]);

            $transaction->update([
                'status'      => 'refunded',
                'refunded_at' => now(),
            ]);
        });
    }

    /**
     * Create 3 default milestones (40% / 40% / 20%) for a newly funded project.
     * Entrepreneurs can override via a future admin UI; this just ensures the
     * escrow structure exists so the frontend has something to render.
     */
    protected function seedDefaultMilestones(Project $project, Investment $investment): void
    {
        $splits = [
            ['position' => 1, 'title' => 'Démarrage du projet',  'percentage' => 40, 'due' => now()->addMonth()],
            ['position' => 2, 'title' => 'Mi-parcours',          'percentage' => 40, 'due' => now()->addMonths(3)],
            ['position' => 3, 'title' => 'Livraison finale',     'percentage' => 20, 'due' => now()->addMonths(6)],
        ];

        foreach ($splits as $s) {
            $share = round(((float) $investment->amount) * ($s['percentage'] / 100), 2);
            EscrowMilestone::create([
                'project_id'    => $project->id,
                'investment_id' => $investment->id,
                'position'      => $s['position'],
                'title'         => $s['title'],
                'amount'        => $share,
                'currency'      => $investment->currency,
                'percentage'    => $s['percentage'],
                'status'        => 'pending',
                'due_at'        => $s['due']->toDateString(),
            ]);
        }
    }

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
            'senegal' => 'SN', 'cote d ivoire' => 'CI', 'ivory coast' => 'CI',
            'mali' => 'ML', 'burkina faso' => 'BF', 'togo' => 'TG',
            'benin' => 'BJ', 'niger' => 'NE', 'guinee bissau' => 'GW',
            'cameroun' => 'CM', 'cameroon' => 'CM', 'gabon' => 'GA',
            'congo' => 'CG', 'tchad' => 'TD', 'chad' => 'TD',
            'nigeria' => 'NG', 'ghana' => 'GH', 'kenya' => 'KE',
            'france' => 'FR', 'belgique' => 'BE', 'suisse' => 'CH',
            'canada' => 'CA', 'etats unis' => 'US', 'united states' => 'US',
        ];
        return $map[$key] ?? 'SN';
    }
}
