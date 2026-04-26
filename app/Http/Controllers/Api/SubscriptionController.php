<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Services\Payment\Gateways\PayDunyaGateway;
use App\Services\Payment\RefundService;
use App\Services\Payment\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptions,
        protected RefundService       $refunds,
    ) {}

    /**
     * POST /api/subscription/refund — garantie 30 jours.
     */
    public function refund(Request $request): JsonResponse
    {
        $sub = $request->user()->activeSubscription();
        if (!$sub) {
            return response()->json(['message' => 'Aucun abonnement actif.'], 404);
        }

        try {
            $sub = $this->refunds->refundSubscription($sub, $request->user());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'      => 'Remboursement effectué (garantie 30 jours).',
            'subscription' => $sub,
        ]);
    }

    /**
     * List all active plans (public).
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()->get();

        return response()->json(['data' => $plans]);
    }

    /**
     * Get the current user's subscription status.
     */
    public function mySubscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $sub = $user->activeSubscription();
        $planSlug = $user->currentPlanSlug();

        return response()->json([
            'subscription' => $sub,
            'plan_slug' => $planSlug,
            'has_active_subscription' => $user->hasActiveSubscription(),
            'is_refundable' => $sub?->isRefundable() ?? false,
        ]);
    }

    /**
     * Subscribe to a plan.
     *
     * For paid plans, returns a hosted PayDunya invoice URL to redirect the user.
     * For the free plan, activates immediately.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'plan_slug'     => ['required', 'string', 'exists:subscription_plans,slug'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'country'       => ['nullable', 'string', 'max:100'],
            'channel'       => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $result = $this->subscriptions->initiate($user, $data);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Impossible d\'initier le paiement.',
            ], 422);
        }

        if ($result['status'] === 'activated') {
            return response()->json([
                'status'       => 'activated',
                'message'      => 'Plan gratuit activé.',
                'subscription' => $result['subscription'],
            ], 201);
        }

        return response()->json([
            'status'       => 'checkout_required',
            'message'      => 'Redirection vers la page de paiement sécurisée.',
            'transaction_id' => $result['transaction']->id,
            'checkout'     => $result['checkout'],
        ], 201);
    }

    /**
     * Cancel current subscription.
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $sub = $this->subscriptions->cancel($user);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $sub->isRefundable()
                ? 'Abonnement annulé. Remboursement en cours (garantie 30 jours).'
                : 'Abonnement annulé. Vous gardez l\'accès jusqu\'à la fin de la période.',
            'refund'  => $sub->isRefundable(),
        ]);
    }

    /**
     * Called by the frontend after the user returns from PayDunya.
     * Re-verifies the invoice with PayDunya and, if completed, ensures the
     * subscription is activated even if the IPN hasn't landed yet.
     */
    public function verify(Request $request, PayDunyaGateway $gateway): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:200'],
        ]);

        $transaction = Transaction::where('paydunya_token', $data['token'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction introuvable.'], 404);
        }

        $status = $gateway->verifyPayment($data['token']);

        if ($status->isPaid() && $transaction->payment_type === 'subscription') {
            try {
                $this->subscriptions->activate($transaction, $status);
            } catch (\Throwable $e) {
                // Already activated by webhook — ignore.
            }
            $transaction->refresh();
        }

        return response()->json([
            'status'      => $status->status,
            'transaction' => $transaction,
            'receipt_url' => $status->receiptUrl,
        ]);
    }
}
