<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\Transaction;
use App\Services\Payment\Gateways\PayDunyaGateway;
use App\Services\Payment\InstallmentService;
use App\Services\Payment\InvestmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvestmentController extends Controller
{
    public function __construct(
        protected InvestmentService $investments,
        protected InstallmentService $installments,
    ) {}

    /**
     * Create an investment and return a PayDunya checkout URL.
     *
     * If `installments` > 1 is passed, an InstallmentPlan is created instead and
     * the first installment's checkout URL is returned.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'project_id'    => ['required', 'integer', 'exists:projects,id'],
            'amount'        => ['required', 'numeric', 'min:1'],
            'type'          => ['nullable', Rule::in(['equity', 'donation', 'loan', 'reward'])],
            'country'       => ['nullable', 'string', 'max:100'],
            'channel'       => ['nullable', 'string', 'max:50'],
            'installments'  => ['nullable', 'integer', 'min:1', 'max:12'],
            'frequency'     => ['nullable', Rule::in(['weekly', 'biweekly', 'monthly'])],
        ]);

        try {
            $result = $this->investments->initiate($user, $data);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Impossible d\'initier l\'investissement.',
            ], 422);
        }

        $installmentCount = (int) ($data['installments'] ?? 1);

        if ($installmentCount > 1) {
            $investment = $result['investment'];
            try {
                $plan = $this->installments->createPlan(
                    user: $user,
                    payable: $investment,
                    totalAmount: (float) $investment->amount,
                    currency: $investment->currency,
                    totalInstallments: $installmentCount,
                    frequency: $data['frequency'] ?? 'monthly',
                );
                $first = $this->installments->invoiceNext($plan);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Investissement créé, mais la planification a échoué : ' . $e->getMessage(),
                ], 422);
            }

            return response()->json([
                'status'         => 'installments_scheduled',
                'message'        => 'Plan d\'échéances créé. Redirection vers le 1er paiement.',
                'investment_id'  => $investment->id,
                'plan'           => $plan->load('installments'),
                'checkout'       => $first['checkout'],
            ], 201);
        }

        return response()->json([
            'status'         => 'checkout_required',
            'message'        => 'Redirection vers la page de paiement sécurisée.',
            'investment_id'  => $result['investment']->id,
            'transaction_id' => $result['transaction']->id,
            'checkout'       => $result['checkout'],
        ], 201);
    }

    /**
     * List the current user's investments.
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = Investment::with(['project:id,slug,title,country,currency,amount_needed,amount_raised', 'transaction:id,paydunya_receipt_url,paid_at,status'])
            ->where('investor_id', $user->id)
            ->latest()
            ->paginate(20);

        return response()->json($items);
    }

    /**
     * Show a single investment with its escrow milestones and receipt URL.
     */
    public function show(Request $request, Investment $investment): JsonResponse
    {
        $user = $request->user();

        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;
        if ($investment->investor_id !== $user->id && !$isAdmin) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $investment->load(['project:id,slug,title,country,currency,amount_needed,amount_raised', 'milestones', 'transaction']);

        return response()->json(['data' => $investment]);
    }

    /**
     * Verify an investment payment on return from PayDunya.
     * Mirrors SubscriptionController::verify.
     */
    public function verify(Request $request, PayDunyaGateway $gateway): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:200'],
        ]);

        $transaction = Transaction::where('paydunya_token', $data['token'])
            ->where('user_id', $request->user()->id)
            ->where('payment_type', 'investment')
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction introuvable.'], 404);
        }

        $status = $gateway->verifyPayment($data['token']);

        if ($status->isPaid()) {
            try {
                $this->investments->activate($transaction, $status);
            } catch (\Throwable $e) {
                // Already activated by webhook — idempotent.
            }
            $transaction->refresh();
        }

        $investment = Investment::where('transaction_id', $transaction->id)
            ->with(['project:id,slug,title,currency', 'milestones'])
            ->first();

        return response()->json([
            'status'      => $status->status,
            'transaction' => $transaction,
            'investment'  => $investment,
            'receipt_url' => $status->receiptUrl ?? $transaction->paydunya_receipt_url,
        ]);
    }
}
