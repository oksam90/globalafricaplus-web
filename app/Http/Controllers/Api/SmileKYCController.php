<?php

namespace App\Http\Controllers\Api;

use App\Events\AMLFlagged;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitKYCRequest;
use App\Models\AMLScreening;
use App\Models\KYCVerification;
use App\Services\SmileIdentity\SmileIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 2 — public-facing KYC API powered by Smile Identity.
 * Spec § 6.1.
 *
 * Each submission method:
 *   1. Records a PENDING KYCVerification row keyed by partner_job_id (UUID).
 *   2. Calls the Smile API via the service façade.
 *   3. Persists the smile_job_id on success, marks the row processing,
 *      and returns a slim payload to the SPA.
 *
 * The synchronous AML endpoint additionally writes an AMLScreening row
 * inline since the result is available right away (no callback).
 */
class SmileKYCController extends Controller
{
    public function __construct(
        protected SmileIdentityService $smile,
    ) {}

    // ─── POST /api/v1/kyc/basic ─────────────────────────────────────────
    public function basic(SubmitKYCRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        return $this->dispatchSmileSubmission(
            jobType:   'basic_kyc',
            request:   $request,
            data:      $data,
            submitter: fn (string $partnerJobId) => $this->smile->submitBasicKYC(
                userId:       (string) $user->id,
                country:      $data['country'],
                idType:       $data['id_type'],
                idNumber:     $data['id_number'],
                personalInfo: [
                    'first_name'   => $data['first_name'],
                    'last_name'    => $data['last_name'],
                    'dob'          => $data['dob'],
                    'phone_number' => $user->phone,
                ],
            ),
        );
    }

    // ─── POST /api/v1/kyc/biometric ─────────────────────────────────────
    public function biometric(SubmitKYCRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        return $this->dispatchSmileSubmission(
            jobType:   'biometric_kyc',
            request:   $request,
            data:      $data,
            submitter: fn () => $this->smile->submitBiometricKYC(
                userId:       (string) $user->id,
                country:      $data['country'],
                idType:       $data['id_type'],
                idNumber:     $data['id_number'],
                selfieBase64: $data['selfie'],
            ),
        );
    }

    // ─── POST /api/v1/kyc/document ──────────────────────────────────────
    public function document(SubmitKYCRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        return $this->dispatchSmileSubmission(
            jobType:   'document_verification',
            request:   $request,
            data:      $data,
            submitter: fn () => $this->smile->submitDocumentVerification(
                userId:         (string) $user->id,
                country:        $data['country'],
                idType:         $data['id_type'],
                idNumber:       $data['id_number'],
                selfieBase64:   $data['selfie'],
                documentBase64: $data['id_document_front'],
            ),
        );
    }

    // ─── POST /api/v1/kyc/aml ─── synchronous ──────────────────────────
    public function aml(SubmitKYCRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        try {
            $result = $this->smile->submitAMLCheck(
                userId:    (string) $user->id,
                fullName:  $data['full_name'],
                countries: $data['countries'],
                birthYear: $data['birth_year'],
            );
        } catch (\Throwable $e) {
            Log::error('Smile AML submission failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Échec du screening AML : ' . $e->getMessage()], 502);
        }

        $raw                 = $result['raw'] ?? [];
        $sanctionsMatch      = (bool) ($raw['sanctions_match'] ?? $raw['SanctionsMatch'] ?? false);
        $pepMatch            = (bool) ($raw['pep_match'] ?? $raw['PEPMatch'] ?? false);
        $adverseMediaMatch   = (bool) ($raw['adverse_media_match'] ?? $raw['AdverseMediaMatch'] ?? false);
        $matches             = $raw['matches'] ?? $raw['Matches'] ?? null;

        $riskLevel = AMLScreening::deriveRiskLevel(
            $sanctionsMatch,
            $pepMatch,
            $adverseMediaMatch,
            is_array($matches) ? $matches : null,
        );

        // Audit 2026-05 — dispatch the event after commit (afterCommit) so
        // listeners cannot observe a half-written state if the transaction
        // rolls back later, but the dispatch decision happens inside the
        // transaction once the screening row is persisted.
        $screening = DB::transaction(function () use ($user, $data, $result, $raw, $sanctionsMatch, $pepMatch, $adverseMediaMatch, $matches, $riskLevel) {
            $screening = AMLScreening::create([
                'user_id'             => $user->id,
                'kyc_verification_id' => optional($user->latestSmileVerification())->id,
                'smile_job_id'        => $result['smile_job_id'] ?? null,
                'full_name_screened'  => $data['full_name'],
                'countries'           => $data['countries'],
                'birth_year'          => $data['birth_year'],
                'result_code'         => (string) ($raw['ResultCode'] ?? $raw['result_code'] ?? ''),
                'sanctions_match'     => $sanctionsMatch,
                'pep_match'           => $pepMatch,
                'adverse_media_match' => $adverseMediaMatch,
                'matches'             => $matches,
                'risk_level'          => $riskLevel,
            ]);

            // Mirror onto the user; "blocked" is reserved for human / regulatory action.
            $user->update([
                'aml_status'         => $sanctionsMatch || $riskLevel === 'critical' ? 'flagged' : 'clear',
                'aml_last_checked_at' => now(),
            ]);

            // Sprint 4 + audit 2026-05 — register a post-commit dispatch hook
            // so listeners only run after the screening row is durable.
            if ($screening->hasAnyMatch()) {
                DB::afterCommit(fn () => AMLFlagged::dispatch($user, $screening));
            }

            return $screening;
        });

        return response()->json([
            'message'    => 'Screening AML effectué.',
            'screening'  => $screening,
            'risk_level' => $riskLevel,
            'flags'      => [
                'sanctions'     => $sanctionsMatch,
                'pep'           => $pepMatch,
                'adverse_media' => $adverseMediaMatch,
            ],
        ], 201);
    }

    // ─── GET /api/v1/kyc/status ─────────────────────────────────────────
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $latest = $user->latestSmileVerification();

        return response()->json([
            'kyc_level'           => $user->kyc_level,
            'kyc_verified_at'     => $user->kyc_verified_at,
            'kyc_expires_at'      => $user->kyc_expires_at,
            'is_expired'          => $user->kyc_expires_at !== null && $user->kyc_expires_at->isPast(),
            'aml_status'          => $user->aml_status,
            'aml_last_checked_at' => $user->aml_last_checked_at,
            'selfie_registered'   => (bool) $user->selfie_registered,
            'latest'              => $latest,
        ]);
    }

    // ─── GET /api/v1/kyc/history ────────────────────────────────────────
    public function history(Request $request): JsonResponse
    {
        $items = KYCVerification::where('user_id', $request->user()->id)
            ->latest('submitted_at')
            ->paginate(20);

        return response()->json($items);
    }

    // ─── POST /api/v1/kyc/web-token ─────────────────────────────────────
    public function webToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => ['nullable', 'string', 'in:biometric_kyc,doc_verification,authentication,basic_kyc,enhanced_kyc'],
        ]);

        try {
            $token = $this->smile->generateWebToken(
                userId:  (string) $request->user()->id,
                product: $data['product'] ?? 'biometric_kyc',
            );
        } catch (\Throwable $e) {
            Log::error('Smile web-token failed', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Génération du token impossible : ' . $e->getMessage()], 502);
        }

        return response()->json($token, 201);
    }

    // ═════════════════════════════════════════════════════════════════════
    // Internals
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Shared scaffolding for the 3 async jobs (basic / biometric / document).
     * Creates a pending KYCVerification, calls Smile, then promotes the row
     * to processing on success or rejected on transport error.
     *
     * @param  callable(string $partnerJobId): array  $submitter
     */
    protected function dispatchSmileSubmission(
        string   $jobType,
        Request  $request,
        array    $data,
        callable $submitter,
    ): JsonResponse {
        $user = $request->user();

        $verification = KYCVerification::create([
            'user_id'        => $user->id,
            'partner_job_id' => (string) \Illuminate\Support\Str::uuid(),
            'job_type'       => $jobType,
            'country'        => $data['country'],
            'id_type'        => $data['id_type'],
            'id_number_hash' => KYCVerification::hashIdNumber($data['id_number']),
            'status'         => 'pending',
            'submitted_at'   => now(),
        ]);

        try {
            $result = $submitter($verification->partner_job_id);
        } catch (\Throwable $e) {
            $verification->update([
                'status'       => 'rejected',
                'result_text'  => 'submission_failed: ' . $e->getMessage(),
                'completed_at' => now(),
            ]);
            Log::error('Smile KYC submission failed', [
                'user_id'  => $user->id,
                'job_type' => $jobType,
                'error'    => $e->getMessage(),
            ]);
            return response()->json([
                'message'      => 'Échec de soumission KYC : ' . $e->getMessage(),
                'verification' => $verification,
            ], 502);
        }

        $verification->update([
            'smile_job_id' => $result['smile_job_id'] ?? null,
            'status'       => 'processing',
        ]);

        return response()->json([
            'message'      => 'Vérification soumise. Résultat attendu via callback.',
            'verification' => $verification->fresh(),
            'smile'        => [
                'job_id'        => $result['job_id'],
                'smile_job_id'  => $result['smile_job_id'] ?? null,
                'status'        => $result['status'] ?? 'submitted',
            ],
        ], 202);
    }
}
