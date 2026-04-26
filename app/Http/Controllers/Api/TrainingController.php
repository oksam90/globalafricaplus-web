<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\TrainingPurchase;
use App\Models\Transaction;
use App\Services\Payment\Gateways\PayDunyaGateway;
use App\Services\Payment\RefundService;
use App\Services\Payment\TrainingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 5 — Catalogue + achat des formations.
 */
class TrainingController extends Controller
{
    public function __construct(
        protected TrainingService $trainings,
        protected RefundService   $refunds,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = Training::published()
            ->select('id', 'title', 'slug', 'summary', 'cover_image', 'category', 'level', 'duration_minutes', 'price', 'currency', 'rating_avg', 'purchases_count');

        if ($category = $request->query('category')) {
            $q->where('category', $category);
        }
        if ($level = $request->query('level')) {
            $q->where('level', $level);
        }
        if ($search = $request->query('q')) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$search}%")
                ->orWhere('summary', 'like', "%{$search}%"));
        }

        return response()->json($q->orderByDesc('purchases_count')->paginate(20));
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $training = Training::published()
            ->where('slug', $slug)
            ->with('instructor:id,name,avatar')
            ->firstOrFail();

        $hasAccess = false;
        if ($user = $request->user()) {
            $hasAccess = $this->trainings->hasAccess($user, $training);
        }

        return response()->json([
            'data'        => $training,
            'has_access'  => $hasAccess,
            'content_url' => $hasAccess ? $training->content_url : null,
        ]);
    }

    /**
     * POST /api/trainings/{slug}/purchase
     */
    public function purchase(Request $request, string $slug): JsonResponse
    {
        $training = Training::published()->where('slug', $slug)->firstOrFail();

        $opts = $request->validate([
            'country' => ['nullable', 'string', 'max:100'],
            'channel' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $result = $this->trainings->initiate($request->user(), $training, $opts);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status'        => 'checkout_required',
            'purchase_id'   => $result['purchase']->id,
            'transaction_id' => $result['transaction']->id,
            'checkout'      => $result['checkout'],
        ], 201);
    }

    /**
     * POST /api/trainings/verify  (return-from-PayDunya verification, like investments)
     */
    public function verify(Request $request, PayDunyaGateway $gateway): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:200'],
        ]);

        $transaction = Transaction::where('paydunya_token', $data['token'])
            ->where('user_id', $request->user()->id)
            ->where('payment_type', 'training')
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction introuvable.'], 404);
        }

        $status = $gateway->verifyPayment($data['token']);
        if ($status->isPaid()) {
            try { $this->trainings->activate($transaction, $status); }
            catch (\Throwable $e) { /* idempotent */ }
            $transaction->refresh();
        }

        $purchase = TrainingPurchase::where('transaction_id', $transaction->id)
            ->with('training:id,slug,title,content_url')
            ->first();

        return response()->json([
            'status'      => $status->status,
            'transaction' => $transaction,
            'purchase'    => $purchase,
        ]);
    }

    /**
     * GET /api/trainings/mine — mes achats
     */
    public function mine(Request $request): JsonResponse
    {
        $items = TrainingPurchase::where('user_id', $request->user()->id)
            ->with('training:id,slug,title,cover_image,duration_minutes')
            ->latest()
            ->paginate(20);

        return response()->json($items);
    }

    /**
     * POST /api/trainings/purchases/{purchase}/refund — garantie 30j
     */
    public function refund(Request $request, TrainingPurchase $purchase): JsonResponse
    {
        try {
            $purchase = $this->refunds->refundTrainingPurchase($purchase, $request->user());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'  => 'Remboursement effectué. L\'accès à la formation a été révoqué.',
            'purchase' => $purchase,
        ]);
    }
}
