<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\Transaction;
use App\Services\Convention\ConventionGenerator;
use App\Services\Convention\ConventionSignatureService;
use App\Services\Payment\Gateways\PayDunyaGateway;
use App\Services\Payment\InstallmentService;
use App\Services\Payment\InvestmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Download the auto-generated investment contract (.docx).
     * Regenerates on demand if missing (e.g. activated before the feature shipped).
     */
    public function contract(Request $request, Investment $investment, ConventionGenerator $generator): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;
        if ($investment->investor_id !== $user->id && !$isAdmin) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        if (!in_array($investment->status, ['escrow', 'released'], true)) {
            return response()->json(['message' => "La convention n'est disponible qu'après confirmation du paiement."], 422);
        }

        $disk = (string) config('conventions.disk', 'local');
        $wantsPdf = $request->query('format') === 'pdf';

        // (Re)génère si le fichier demandé manque (docx, ou pdf si demandé).
        $missing = !$investment->contract_path || !Storage::disk($disk)->exists($investment->contract_path);
        if ($wantsPdf && (!$investment->contract_pdf_path || !Storage::disk($disk)->exists($investment->contract_pdf_path))) {
            $missing = true;
        }
        if ($missing) {
            try {
                $generator->generateForInvestment($investment->fresh());
                $investment->refresh();
            } catch (\Throwable $e) {
                return response()->json(['message' => 'Génération de la convention impossible : ' . $e->getMessage()], 500);
            }
        }

        $base = 'Convention_' . ($investment->contract_type ?: $investment->type) . '_' . $investment->id;

        // PDF demandé et disponible → on sert le PDF ; sinon repli sur le Word.
        if ($wantsPdf && $investment->contract_pdf_path && Storage::disk($disk)->exists($investment->contract_pdf_path)) {
            return Storage::disk($disk)->download($investment->contract_pdf_path, $base . '.pdf');
        }

        return Storage::disk($disk)->download($investment->contract_path, $base . '.docx');
    }

    /**
     * Envoie (ou ré-envoie) la convention à la signature électronique Yousign.
     */
    public function sendForSignature(Request $request, Investment $investment, ConventionSignatureService $signatures): JsonResponse
    {
        if (!$this->canManageContract($request, $investment)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        if (!in_array($investment->status, ['escrow', 'released'], true)) {
            return response()->json(['message' => "La signature n'est disponible qu'après confirmation du paiement."], 422);
        }

        try {
            $signatures->sendForSignature($investment);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'              => 'Convention envoyée à la signature des deux parties.',
            'contract_status'      => $investment->fresh()->contract_status,
            'signature_request_id' => $investment->fresh()->signature_request_id,
        ]);
    }

    /**
     * Rafraîchit l'état de signature depuis Yousign (et récupère le PDF signé).
     */
    public function refreshSignature(Request $request, Investment $investment, ConventionSignatureService $signatures): JsonResponse
    {
        if (!$this->canManageContract($request, $investment)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        try {
            $status = $signatures->syncStatus($investment);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'provider_status' => $status,
            'contract_status' => $investment->fresh()->contract_status,
            'signed'          => $investment->fresh()->contract_status === 'signed',
        ]);
    }

    /**
     * Télécharge le PDF final signé par les deux parties.
     */
    public function downloadSigned(Request $request, Investment $investment): StreamedResponse|JsonResponse
    {
        if (!$this->canManageContract($request, $investment)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $disk = (string) config('conventions.disk', 'local');
        if (!$investment->contract_signed_path || !Storage::disk($disk)->exists($investment->contract_signed_path)) {
            return response()->json(['message' => 'Convention signée non disponible.'], 404);
        }

        $filename = 'Convention_signee_' . ($investment->contract_type ?: $investment->type) . '_' . $investment->id . '.pdf';
        return Storage::disk($disk)->download($investment->contract_signed_path, $filename);
    }

    /** Le porteur (investisseur) du contrat ou un admin. */
    private function canManageContract(Request $request, Investment $investment): bool
    {
        $user = $request->user();
        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;
        return $investment->investor_id === $user->id || $isAdmin;
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
