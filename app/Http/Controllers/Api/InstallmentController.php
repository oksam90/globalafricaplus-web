<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstallmentPlan;
use App\Models\Investment;
use App\Services\Payment\InstallmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sprint 5 — Plans de paiement fractionné.
 */
class InstallmentController extends Controller
{
    public function __construct(
        protected InstallmentService $installments,
    ) {}

    /**
     * POST /api/investments/{investment}/installments
     * Configure un plan d'échéances pour un investissement déjà initié (status pending).
     */
    public function createForInvestment(Request $request, Investment $investment): JsonResponse
    {
        if ($investment->investor_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        if (!in_array($investment->status, ['pending', 'failed'], true)) {
            return response()->json(['message' => "L'investissement n'est plus modifiable (statut : {$investment->status})."], 422);
        }

        $data = $request->validate([
            'installments' => ['required', 'integer', 'min:2', 'max:12'],
            'frequency'    => ['nullable', Rule::in(['weekly', 'biweekly', 'monthly'])],
        ]);

        try {
            $plan = $this->installments->createPlan(
                user: $request->user(),
                payable: $investment,
                totalAmount: (float) $investment->amount,
                currency: $investment->currency,
                totalInstallments: (int) $data['installments'],
                frequency: $data['frequency'] ?? 'monthly',
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Génère immédiatement le 1er invoice pour rediriger l'utilisateur.
        try {
            $first = $this->installments->invoiceNext($plan);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => "Plan créé mais facturation échouée : {$e->getMessage()}",
                'plan'    => $plan->load('installments'),
            ], 207);
        }

        return response()->json([
            'plan'     => $plan->load('installments'),
            'checkout' => $first['checkout'],
        ], 201);
    }

    /**
     * GET /api/installments/mine — mes plans actifs
     */
    public function mine(Request $request): JsonResponse
    {
        $plans = InstallmentPlan::where('user_id', $request->user()->id)
            ->with('installments')
            ->latest()
            ->paginate(20);

        return response()->json($plans);
    }

    /**
     * POST /api/installments/{plan}/pay-next — relance manuelle de la prochaine échéance
     */
    public function payNext(Request $request, InstallmentPlan $plan): JsonResponse
    {
        if ($plan->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        if ($plan->status !== 'active') {
            return response()->json(['message' => "Plan non actif (statut : {$plan->status})."], 422);
        }

        try {
            $result = $this->installments->invoiceNext($plan);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'installment' => $result['installment'],
            'checkout'    => $result['checkout'],
        ]);
    }
}
