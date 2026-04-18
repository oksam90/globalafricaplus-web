<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KycController extends Controller
{
    // ─── Status / Current session ───────────────────────────

    /**
     * Get the user's KYC status and latest session data.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = $user->latestKycSession();

        return response()->json([
            'kyc_level' => $user->kyc_level,
            'is_verified' => $user->isKycVerified(),
            'has_pending' => $user->hasKycPending(),
            'session' => $session ? [
                'id' => $session->id,
                'status' => $session->status,
                'current_step' => $session->current_step,
                'person_type' => $session->person_type,
                'completion' => $session->completionPercent(),
                'step1_complete' => $session->step1Complete(),
                'step2_complete' => $session->step2Complete(),
                'step3_complete' => $session->step3Complete(),
                'step4_complete' => $session->step4Complete(),
                'rejection_reason' => $session->rejection_reason,
                'redirect_url' => $session->redirect_url,
                'created_at' => $session->created_at,
                'verified_at' => $session->verified_at,
            ] : null,
        ]);
    }

    // ─── Step 1: Identity data collection ───────────────────

    /**
     * Start a new KYC session or update step 1 (identity data).
     */
    public function saveStep1(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'person_type' => ['required', 'in:physical,moral'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['required_if:person_type,physical', 'nullable', 'date', 'before:today'],
            'place_of_birth' => ['nullable', 'string', 'max:120'],
            'nationality' => ['required', 'string', 'max:80'],
            'gender' => ['nullable', 'in:male,female,other'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:30'],
            // Moral person fields
            'company_name' => ['required_if:person_type,moral', 'nullable', 'string', 'max:200'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_registration_number' => ['required_if:person_type,moral', 'nullable', 'string', 'max:80'],
            'legal_form' => ['nullable', 'in:sarl,sa,sas,suarl,gie,ei,other'],
            'legal_representative_name' => ['nullable', 'string', 'max:200'],
        ]);

        // Get or create active session
        $session = $this->getOrCreateSession($user);

        $session->fill($data);
        $session->current_step = max($session->current_step, 1);
        $session->status = 'in_progress';
        $session->save();

        return response()->json([
            'message' => 'Informations d\'identité enregistrées.',
            'session' => $this->sessionPayload($session),
        ]);
    }

    // ─── Step 2: Document verification ──────────────────────

    /**
     * Save step 2 (document info). Actual file uploads handled separately.
     */
    public function saveStep2(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = $this->getActiveSession($user);

        if (!$session) {
            return response()->json(['message' => 'Veuillez d\'abord compléter l\'étape 1.'], 422);
        }

        $data = $request->validate([
            'document_type' => ['required', 'in:cni,passport,permis,carte_sejour'],
            'document_number' => ['required', 'string', 'max:50'],
            'document_expiry' => ['required', 'date', 'after:today'],
            'document_issuing_country' => ['required', 'string', 'max:80'],
        ]);

        $session->fill($data);
        $session->current_step = max($session->current_step, 2);
        $session->save();

        return response()->json([
            'message' => 'Informations du document enregistrées.',
            'session' => $this->sessionPayload($session),
        ]);
    }

    /**
     * Upload KYC documents (front, back, selfie, proof_of_address, rccm, statuts).
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = $this->getActiveSession($user);

        if (!$session) {
            return response()->json(['message' => 'Aucune session KYC active.'], 422);
        }

        $request->validate([
            'type' => ['required', 'in:document_front,document_back,selfie,proof_of_address,rccm,statuts'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $type = $request->input('type');
        $path = $request->file('file')->store("kyc/{$user->id}", 'public');
        $session->update(["{$type}_url" => $path]);

        return response()->json([
            'message' => 'Document téléversé.',
            'field' => $type,
            'url' => $path,
            'session' => $this->sessionPayload($session),
        ]);
    }

    // ─── Step 3: Risk assessment ────────────────────────────

    public function saveStep3(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = $this->getActiveSession($user);

        if (!$session) {
            return response()->json(['message' => 'Veuillez compléter les étapes précédentes.'], 422);
        }

        $data = $request->validate([
            'source_of_funds' => ['required', 'string', 'max:120'],
            'occupation' => ['required', 'string', 'max:120'],
            'expected_monthly_volume' => ['required', 'numeric', 'min:0'],
            'is_pep' => ['required', 'boolean'],
        ]);

        // Automated risk scoring
        $riskLevel = 'low';
        $riskFactors = [];

        if ($data['is_pep']) {
            $riskLevel = 'high';
            $riskFactors[] = 'Personne politiquement exposée (PPE)';
        }
        if ($data['expected_monthly_volume'] > 50000) {
            $riskLevel = $riskLevel === 'low' ? 'medium' : $riskLevel;
            $riskFactors[] = 'Volume mensuel élevé (> 50 000 €)';
        }
        if ($data['expected_monthly_volume'] > 200000) {
            $riskLevel = 'high';
            $riskFactors[] = 'Volume mensuel très élevé (> 200 000 €)';
        }

        $session->fill($data);
        $session->risk_level = $riskLevel;
        $session->risk_factors = $riskFactors;
        $session->current_step = max($session->current_step, 3);
        $session->save();

        return response()->json([
            'message' => 'Évaluation du risque enregistrée.',
            'risk_level' => $riskLevel,
            'session' => $this->sessionPayload($session),
        ]);
    }

    // ─── Step 4: Submit for AML check & IDNorm verification ─

    /**
     * Submit the KYC for final AML check and IDNorm verification.
     */
    public function submitForVerification(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = $this->getActiveSession($user);

        if (!$session) {
            return response()->json(['message' => 'Aucune session KYC active.'], 422);
        }

        if (!$session->step1Complete() || !$session->step2Complete() || !$session->step3Complete()) {
            return response()->json(['message' => 'Veuillez compléter toutes les étapes avant de soumettre.'], 422);
        }

        // ── Simulate AML screening (in DEV mode) ──
        $isDev = config('services.idnorm.mode') === 'dev';

        if ($isDev) {
            // DEV mode: auto-approve AML
            $session->aml_checked = true;
            $session->aml_clear = true;
            $session->sanctions_clear = true;
            $session->pep_clear = !$session->is_pep;
            $session->aml_results = [
                'mode' => 'dev_simulation',
                'sanctions_matches' => 0,
                'pep_matches' => $session->is_pep ? 1 : 0,
                'adverse_media' => 0,
                'checked_at' => now()->toISOString(),
            ];
            $session->current_step = 4;
            $session->status = 'documents_submitted';

            // Create a simulated IDNorm session
            $session->provider_session_id = 'idnorm_dev_' . Str::random(24);
            $session->redirect_url = url('/kyc/verification/' . $session->id);
            $session->save();

            // In DEV mode, auto-verify after a brief delay (simulated)
            // In production, IDNorm calls our webhook
            $this->devAutoVerify($session, $user);

            return response()->json([
                'message' => 'Vérification KYC soumise avec succès. (Mode DEV: vérification automatique)',
                'redirect_url' => $session->redirect_url,
                'session' => $this->sessionPayload($session),
            ]);
        }

        // ── PRODUCTION: Call IDNorm API ──
        $session->current_step = 4;
        $session->status = 'documents_submitted';
        $session->save();

        try {
            $idnormResponse = $this->createIdnormSession($session, $user);

            $session->provider_session_id = $idnormResponse['session_id'] ?? null;
            $session->redirect_url = $idnormResponse['verification_url'] ?? null;
            $session->save();

            return response()->json([
                'message' => 'Session de vérification IDNorm créée.',
                'redirect_url' => $session->redirect_url,
                'session' => $this->sessionPayload($session),
            ]);
        } catch (\Exception $e) {
            Log::error('IDNorm session creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur lors de la création de la session IDNorm. Veuillez réessayer.',
            ], 500);
        }
    }

    // ─── IDNorm Webhook ─────────────────────────────────────

    /**
     * Webhook called by IDNorm when verification is complete.
     * This endpoint is public (no auth) but validated via webhook secret.
     */
    public function webhook(Request $request): JsonResponse
    {
        // Validate webhook signature
        $signature = $request->header('X-IDNorm-Signature');
        $secret = config('services.idnorm.webhook_secret');
        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expectedSignature, $signature ?? '')) {
            Log::warning('IDNorm webhook: invalid signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = $request->all();
        $sessionId = $data['session_id'] ?? null;

        $session = KycSession::where('provider_session_id', $sessionId)->first();
        if (!$session) {
            Log::warning('IDNorm webhook: session not found', ['session_id' => $sessionId]);
            return response()->json(['error' => 'Session not found'], 404);
        }

        $status = $data['status'] ?? 'unknown';
        $session->provider_data = $data;

        if ($status === 'approved' || $status === 'verified') {
            $session->status = 'verified';
            $session->verified_at = now();
            $session->aml_checked = true;
            $session->aml_clear = $data['aml_clear'] ?? true;
            $session->sanctions_clear = $data['sanctions_clear'] ?? true;
            $session->pep_clear = $data['pep_clear'] ?? true;

            // Update user KYC level
            $session->user->update(['kyc_level' => 'verified']);

        } elseif ($status === 'rejected' || $status === 'failed') {
            $session->status = 'rejected';
            $session->rejection_reason = $data['rejection_reason'] ?? 'Vérification échouée';
        }

        $session->save();

        Log::info('IDNorm webhook processed', [
            'session_id' => $sessionId,
            'status' => $status,
            'user_id' => $session->user_id,
        ]);

        return response()->json(['received' => true]);
    }

    // ─── Get session data (for loading form state) ──────────

    public function sessionData(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = $this->getActiveSession($user) ?? $user->latestKycSession();

        if (!$session) {
            return response()->json(['session' => null]);
        }

        return response()->json([
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
                'current_step' => $session->current_step,
                'completion' => $session->completionPercent(),
                'step1_complete' => $session->step1Complete(),
                'step2_complete' => $session->step2Complete(),
                'step3_complete' => $session->step3Complete(),
                'step4_complete' => $session->step4Complete(),
                'person_type' => $session->person_type,
                // Step 1
                'first_name' => $session->first_name,
                'last_name' => $session->last_name,
                'date_of_birth' => $session->date_of_birth?->format('Y-m-d'),
                'place_of_birth' => $session->place_of_birth,
                'nationality' => $session->nationality,
                'gender' => $session->gender,
                'address' => $session->address,
                'city' => $session->city,
                'postal_code' => $session->postal_code,
                'country' => $session->country,
                'phone' => $session->phone,
                'company_name' => $session->company_name,
                'company_address' => $session->company_address,
                'company_registration_number' => $session->company_registration_number,
                'legal_form' => $session->legal_form,
                'legal_representative_name' => $session->legal_representative_name,
                // Step 2
                'document_type' => $session->document_type,
                'document_number' => $session->document_number,
                'document_expiry' => $session->document_expiry?->format('Y-m-d'),
                'document_issuing_country' => $session->document_issuing_country,
                'document_front_url' => $session->document_front_url,
                'document_back_url' => $session->document_back_url,
                'selfie_url' => $session->selfie_url,
                'proof_of_address_url' => $session->proof_of_address_url,
                'rccm_url' => $session->rccm_url,
                'statuts_url' => $session->statuts_url,
                // Step 3
                'source_of_funds' => $session->source_of_funds,
                'occupation' => $session->occupation,
                'expected_monthly_volume' => $session->expected_monthly_volume,
                'is_pep' => $session->is_pep,
                'risk_level' => $session->risk_level,
                'risk_factors' => $session->risk_factors,
                // Step 4
                'aml_checked' => $session->aml_checked,
                'aml_clear' => $session->aml_clear,
                'sanctions_clear' => $session->sanctions_clear,
                'pep_clear' => $session->pep_clear,
                // Result
                'rejection_reason' => $session->rejection_reason,
                'verified_at' => $session->verified_at,
                'redirect_url' => $session->redirect_url,
            ],
        ]);
    }

    // ─── Private helpers ────────────────────────────────────

    private function getOrCreateSession($user): KycSession
    {
        // Reuse pending/in_progress session
        $session = $user->kycSessions()
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest()
            ->first();

        if (!$session) {
            $session = KycSession::create([
                'user_id' => $user->id,
                'provider' => 'idnorm',
                'status' => 'pending',
                'current_step' => 0,
            ]);
        }

        return $session;
    }

    private function getActiveSession($user): ?KycSession
    {
        return $user->kycSessions()
            ->whereIn('status', ['pending', 'in_progress', 'documents_submitted'])
            ->latest()
            ->first();
    }

    private function sessionPayload(KycSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'current_step' => $session->current_step,
            'completion' => $session->completionPercent(),
            'step1_complete' => $session->step1Complete(),
            'step2_complete' => $session->step2Complete(),
            'step3_complete' => $session->step3Complete(),
            'step4_complete' => $session->step4Complete(),
            'risk_level' => $session->risk_level,
            'redirect_url' => $session->redirect_url,
            'verified_at' => $session->verified_at,
        ];
    }

    /**
     * DEV mode: auto-verify the session immediately.
     */
    private function devAutoVerify(KycSession $session, $user): void
    {
        $session->status = 'verified';
        $session->verified_at = now();
        $session->provider_data = [
            'mode' => 'dev_auto_verify',
            'verified_at' => now()->toISOString(),
        ];
        $session->save();

        $user->update(['kyc_level' => 'verified']);
    }

    /**
     * Create a verification session at IDNorm (PRODUCTION mode).
     */
    private function createIdnormSession(KycSession $session, $user): array
    {
        $config = config('services.idnorm');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key'],
            'X-API-Secret' => $config['api_secret'],
            'Content-Type' => 'application/json',
        ])->post($config['base_url'] . '/sessions', [
            'reference_id' => 'africaplus_' . $user->id . '_' . $session->id,
            'type' => $session->person_type === 'moral' ? 'business' : 'individual',
            'callback_url' => url('/api/kyc/webhook'),
            'redirect_url' => url('/kyc/verification/' . $session->id),
            'person' => [
                'first_name' => $session->first_name,
                'last_name' => $session->last_name,
                'date_of_birth' => $session->date_of_birth?->format('Y-m-d'),
                'nationality' => $session->nationality,
                'email' => $user->email,
                'phone' => $session->phone,
            ],
            'document' => [
                'type' => $session->document_type,
                'number' => $session->document_number,
                'country' => $session->document_issuing_country,
            ],
            'checks' => ['identity', 'document', 'aml', 'sanctions', 'pep'],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('IDNorm API error: ' . $response->body());
        }

        return $response->json();
    }
}
