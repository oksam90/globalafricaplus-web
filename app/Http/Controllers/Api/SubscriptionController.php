<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
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
     * Subscribe to a plan (or upgrade/change plan).
     * In production, this would integrate Stripe/Flutterwave.
     * For now, it activates immediately with a 30-day trial guarantee.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'plan_slug' => ['required', 'string', 'exists:subscription_plans,slug'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'payment_method' => ['nullable', Rule::in(['stripe', 'flutterwave', 'mobile_money', 'card'])],
        ]);

        $plan = SubscriptionPlan::where('slug', $data['plan_slug'])->firstOrFail();

        // Cancel any existing active subscription
        Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'trialing'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        // Determine pricing
        $price = $data['billing_cycle'] === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
        $endsAt = $data['billing_cycle'] === 'yearly'
            ? now()->addYear()
            : now()->addMonth();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $data['billing_cycle'],
            'status' => $plan->isFree() ? 'active' : 'active',
            'starts_at' => now(),
            'ends_at' => $plan->isFree() ? null : $endsAt,
            'trial_ends_at' => $plan->isFree() ? null : now()->addDays(30),
            'payment_method' => $data['payment_method'] ?? null,
        ]);

        return response()->json([
            'subscription' => $subscription->load('plan'),
            'message' => $plan->isFree()
                ? 'Plan gratuit activé.'
                : 'Abonnement activé avec garantie satisfait ou remboursé de 30 jours.',
        ], 201);
    }

    /**
     * Cancel current subscription.
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $sub = $user->activeSubscription();

        if (!$sub || $sub->plan->isFree()) {
            return response()->json(['message' => 'Aucun abonnement payant à annuler.'], 422);
        }

        $isRefundable = $sub->isRefundable();

        $sub->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'message' => $isRefundable
                ? 'Abonnement annulé. Remboursement en cours (garantie 30 jours).'
                : 'Abonnement annulé. Vous gardez l\'accès jusqu\'à la fin de la période.',
            'refund' => $isRefundable,
        ]);
    }
}
