<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\Project;
use App\Models\Transaction;
use App\Services\Convention\ConventionGenerator;
use App\Services\Convention\ConventionSignatureService;
use App\Services\Payment\FeeCalculator;
use App\Services\Payment\InstallmentService;
use App\Services\Payment\InvestmentService;
use App\Services\Payment\PaymentGatewayFactory;
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
            'project_id'      => ['required', 'integer', 'exists:projects,id'],
            // « Montant Reçu » — ce que le porteur de projet encaisse.
            // `amount` reste accepté (compatibilité) et vaut alors le montant
            // reçu exprimé dans la devise du projet.
            'net_amount'      => ['required_without:amount', 'numeric', 'min:1'],
            'amount'          => ['required_without:net_amount', 'numeric', 'min:1'],
            'amount_currency' => ['nullable', 'string', 'size:3'],
            'type'            => ['nullable', Rule::in(['equity', 'donation', 'loan', 'reward'])],
            // Moyen de paiement : mobile money (PawaPay) ou carte bancaire (PayDunya)
            'payment_method'  => ['nullable', Rule::in(array_keys(config('payments.methods', [])))],
            'country'         => ['nullable', 'string', 'max:100'],
            'channel'         => ['nullable', 'string', 'max:50'],
            'provider'        => ['nullable', 'string', 'max:40'],
            'installments'    => ['nullable', 'integer', 'min:1', 'max:12'],
            'frequency'       => ['nullable', Rule::in(['weekly', 'biweekly', 'monthly'])],
        ]);

        $installmentCount = (int) ($data['installments'] ?? 1);

        // En paiement fractionné, le règlement passe par les échéances : inutile
        // d'ouvrir un checkout pour la totalité, il serait immédiatement
        // abandonné (et laissait un dépôt orphelin chez le PSP).
        if ($installmentCount > 1) {
            $data['skip_checkout'] = true;
        }

        try {
            $result = $this->investments->initiate($user, $data);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Impossible d\'initier l\'investissement.',
            ], 422);
        }

        if ($installmentCount > 1) {
            $investment = $result['investment'];
            try {
                $plan = $this->installments->createPlan(
                    user: $user,
                    payable: $investment,
                    // Le plan porte sur le MONTANT ENVOYÉ (frais + commission
                    // inclus), pas sur le montant reçu par le porteur : sinon
                    // les frais ne seraient jamais encaissés.
                    totalAmount: (float) $investment->charged_amount,
                    currency: $investment->charged_currency,
                    totalInstallments: $installmentCount,
                    frequency: $data['frequency'] ?? 'monthly',
                    paymentMethod: $result['quote']['method'] ?? null,
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
            'quote'          => $result['quote'] ?? null,
            'checkout'       => $result['checkout'],
        ], 201);
    }

    /**
     * Devis « Montant Reçu → Montant Envoyé ».
     *
     * Renvoie la décomposition complète utilisée par le popup « Investir dans
     * ce projet » : commission GlobalAfrica+ (barème dégressif 3 % / 2 % / 1 %,
     * montants pivots non exposés) + frais PawaPay du pays du projet.
     *
     * Lecture seule : aucune transaction n'est créée.
     */
    public function quote(Request $request, FeeCalculator $fees): JsonResponse
    {
        $data = $request->validate([
            'project_id'     => ['required', 'integer', 'exists:projects,id'],
            'net_amount'     => ['required', 'numeric', 'min:0.01'],
            'currency'       => ['nullable', 'string', 'size:3'],
            'payment_method' => ['nullable', Rule::in(array_keys(config('payments.methods', [])))],
        ]);

        $project = Project::findOrFail($data['project_id']);
        $method  = $data['payment_method'] ?? $fees->defaultMethod();

        try {
            // Un devis par moyen de paiement : le popup affiche les deux
            // options avec leur coût réel avant que l'utilisateur ne choisisse.
            $all = $this->investments->quoteAllMethods(
                $project,
                (float) $data['net_amount'],
                $data['currency'] ?? null,
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data'           => $all[$method] ?? reset($all),
            'methods'        => $fees->availableMethods(),
            'quotes'         => $all,
            'default_method' => $fees->defaultMethod(),
            'minimum'        => $fees->minimumAmount((string) ($all[$method]['currency'] ?? 'XOF')),
        ]);
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
    public function verify(Request $request, PaymentGatewayFactory $gateways): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:200'],
        ]);

        // Le « token » est le token PayDunya OU le depositId PawaPay selon le
        // PSP qui a traité la transaction.
        $transaction = Transaction::where('user_id', $request->user()->id)
            ->where('payment_type', 'investment')
            ->where(function ($q) use ($data) {
                $q->where('paydunya_token', $data['token'])
                  ->orWhere('pawapay_deposit_id', $data['token']);
            })
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction introuvable.'], 404);
        }

        $gateway = $gateways->make($transaction->gateway ?: config('payments.default_gateway', 'pawapay'));
        $status  = $gateway->verifyPayment($data['token']);

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
