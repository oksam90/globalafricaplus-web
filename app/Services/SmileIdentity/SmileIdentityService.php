<?php

namespace App\Services\SmileIdentity;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Façade for the Smile Identity REST API (Sprint 1 — Foundation).
 *
 * Methods map 1:1 to the dashboard's KYC steps:
 *
 *   submitBasicKYC()              → Job Type 5  — POST /id_verification           (synchronous + async callback)
 *   submitBiometricKYC()          → Job Type 1  — POST /upload then PUT S3        (asynchronous)
 *   submitDocumentVerification()  → Job Type 6  — POST /upload then PUT S3        (asynchronous)
 *   submitAMLCheck()              → Job Type 10 — POST /aml_check                 (synchronous)
 *   generateWebToken()            →              POST /token                      (Hosted Web SDK)
 *
 * Each call returns a normalised array. Callers (controllers / jobs) persist
 * the response into kyc_verifications / aml_screenings rows. The actual
 * approval lifecycle is driven by the webhook (see ProcessSmileCallback).
 */
class SmileIdentityService
{
    public const JOB_BIOMETRIC_KYC          = 1;
    public const JOB_BASIC_KYC              = 5;
    public const JOB_DOCUMENT_VERIFICATION  = 6;
    public const JOB_AML_CHECK              = 10;

    public function __construct(
        protected ?string $baseUrl   = null,
        protected ?string $partnerId = null,
    ) {
        $this->baseUrl   ??= (string) config('smile.base_url');
        $this->partnerId ??= (string) config('smile.partner_id');

        if ($this->baseUrl === '' || $this->partnerId === '') {
            throw new RuntimeException('Smile Identity is not configured (SMILE_PARTNER_ID / SMILE_API_KEY missing).');
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Basic KYC — Job Type 5
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Submit a Basic KYC job (no biometrics — name/dob/id_number against the
     * issuing authority). Async — final result arrives via webhook.
     *
     * @param array{first_name:string,last_name:string,dob:string,phone_number?:string} $personalInfo
     * @return array{smile_job_id:?string,job_id:string,status:string,raw:array}
     */
    public function submitBasicKYC(
        string $userId,
        string $country,
        string $idType,
        string $idNumber,
        array  $personalInfo,
    ): array {
        $jobId = (string) Str::uuid();
        $sig   = SmileSignature::generate();

        $payload = $this->envelope($sig, $userId, $jobId, self::JOB_BASIC_KYC, [
            'country'      => strtoupper($country),
            'id_type'      => $idType,
            'id_number'    => $idNumber,
            'first_name'   => $personalInfo['first_name'],
            'last_name'    => $personalInfo['last_name'],
            'dob'          => $personalInfo['dob'],
            'phone_number' => $personalInfo['phone_number'] ?? null,
            'callback_url' => (string) config('smile.callback_url'),
        ]);

        $response = $this->http()->post($this->baseUrl . '/id_verification', $payload);

        return $this->normalize($response, $jobId);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Biometric KYC — Job Type 1   (selfie + facial comparison)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Submit a Biometric KYC job. Two-step:
     *   1) POST /upload         → returns upload_url + smile_job_id
     *   2) PUT  upload_url      → ZIP containing info.json + selfie image
     *
     * The user-facing selfie may be passed as base64; the file extension is
     * inferred from `$selfieMime` (image/png|jpeg).
     *
     * @return array{smile_job_id:string,job_id:string,status:string,raw:array}
     */
    public function submitBiometricKYC(
        string $userId,
        string $country,
        string $idType,
        string $idNumber,
        string $selfieBase64,
        string $selfieMime = 'image/jpeg',
    ): array {
        return $this->submitImageJob(
            jobType:    self::JOB_BIOMETRIC_KYC,
            userId:     $userId,
            country:    $country,
            idType:     $idType,
            idNumber:   $idNumber,
            selfieB64:  $selfieBase64,
            selfieMime: $selfieMime,
            documentB64: null,
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Document Verification — Job Type 6   (OCR + face match against doc photo)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Submit a Document Verification job. Same 2-step upload as biometric KYC,
     * but the ZIP also includes the photo of the ID document.
     *
     * @return array{smile_job_id:string,job_id:string,status:string,raw:array}
     */
    public function submitDocumentVerification(
        string $userId,
        string $country,
        string $idType,
        string $idNumber,
        string $selfieBase64,
        string $documentBase64,
        string $selfieMime   = 'image/jpeg',
        string $documentMime = 'image/jpeg',
    ): array {
        return $this->submitImageJob(
            jobType:     self::JOB_DOCUMENT_VERIFICATION,
            userId:      $userId,
            country:     $country,
            idType:      $idType,
            idNumber:    $idNumber,
            selfieB64:   $selfieBase64,
            selfieMime:  $selfieMime,
            documentB64: $documentBase64,
            documentMime: $documentMime,
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // AML Check — Job Type 10   (SYNCHRONOUS)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Run an AML screening (sanctions / PEP / adverse media). Synchronous —
     * the response carries the result immediately, no callback.
     *
     * @param string[] $countries ISO-2 codes, e.g. ['SN','FR']
     * @return array{smile_job_id:?string,job_id:string,status:string,raw:array}
     */
    public function submitAMLCheck(
        string $userId,
        string $fullName,
        array  $countries,
        string $birthYear,
    ): array {
        $jobId = (string) Str::uuid();
        $sig   = SmileSignature::generate();

        $payload = $this->envelope($sig, $userId, $jobId, self::JOB_AML_CHECK, [
            'full_name'          => $fullName,
            'countries'          => array_map('strtoupper', $countries),
            'birth_year'         => $birthYear,
            'strict_match'       => (bool) config('smile.aml.strict_match'),
            'check_pep'          => (bool) config('smile.aml.check_pep'),
            'check_sanctions'    => (bool) config('smile.aml.check_sanctions'),
            'check_adverse_media' => (bool) config('smile.aml.check_adverse_media'),
        ]);

        $response = $this->http()->post($this->baseUrl . '/aml_check', $payload);

        return $this->normalize($response, $jobId);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Web Token — Hosted Web SDK
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Generate a one-shot web token consumed by the Smile Hosted Web SDK to
     * launch the selfie + document capture flow without exposing the API key
     * to the browser.
     *
     * @return array{token:?string,job_id:string,raw:array}
     */
    public function generateWebToken(string $userId, string $product = 'biometric_kyc'): array
    {
        $jobId = (string) Str::uuid();
        $sig   = SmileSignature::generate();

        $payload = [
            'user_id'      => $userId,
            'job_id'       => $jobId,
            'product'      => $product,
            'partner_id'   => $this->partnerId,
            'timestamp'    => $sig['timestamp'],
            'signature'    => $sig['signature'],
            'callback_url' => (string) config('smile.callback_url'),
        ];

        $response = $this->http()->post($this->baseUrl . '/token', $payload);
        $body     = $this->jsonOrThrow($response);

        return [
            'token'  => $body['token'] ?? null,
            'job_id' => $jobId,
            'raw'    => $body,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════
    // Internals
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Shared 2-step upload routine for biometric & document verification jobs.
     */
    protected function submitImageJob(
        int     $jobType,
        string  $userId,
        string  $country,
        string  $idType,
        string  $idNumber,
        string  $selfieB64,
        string  $selfieMime  = 'image/jpeg',
        ?string $documentB64 = null,
        string  $documentMime = 'image/jpeg',
    ): array {
        $jobId = (string) Str::uuid();
        $sig   = SmileSignature::generate();

        // ── Step 1 — prep upload (returns S3 upload_url + smile_job_id) ──
        $prepPayload = [
            'partner_id'         => $this->partnerId,
            'timestamp'          => $sig['timestamp'],
            'signature'          => $sig['signature'],
            'source_sdk'         => config('smile.sdk.name'),
            'source_sdk_version' => config('smile.sdk.version'),
            'file_name'          => 'submission.zip',
            'smile_client_id'    => $this->partnerId,
            'callback_url'       => (string) config('smile.callback_url'),
            'partner_params'     => [
                'user_id'  => $userId,
                'job_id'   => $jobId,
                'job_type' => $jobType,
            ],
            'model_parameters'   => (object) [],
        ];

        $prepResponse = $this->http()->post($this->baseUrl . '/upload', $prepPayload);
        $prep         = $this->jsonOrThrow($prepResponse);

        $uploadUrl   = $prep['upload_url']   ?? null;
        $smileJobId  = $prep['smile_job_id'] ?? null;

        if (!$uploadUrl || !$smileJobId) {
            throw new RuntimeException('Smile Identity prep-upload returned no upload_url / smile_job_id.');
        }

        // ── Step 2 — build ZIP (info.json + image(s)) and PUT to S3 ──
        $images = [
            ['image_type_id' => 2, 'image' => $selfieB64], // 2 = selfie (base64)
        ];
        if ($documentB64 !== null) {
            $images[] = ['image_type_id' => 3, 'image' => $documentB64]; // 3 = ID-card image (base64)
        }

        $infoJson = json_encode([
            'package_information' => [
                'apiVersion' => ['buildNumber' => 0, 'majorVersion' => 2, 'minorVersion' => 0],
                'language'   => 'php',
            ],
            'id_info' => [
                'country'   => strtoupper($country),
                'id_type'   => $idType,
                'id_number' => $idNumber,
                'entered'   => true,
            ],
            'images' => $images,
        ], JSON_UNESCAPED_SLASHES);

        $zip = $this->buildZipPackage($infoJson);

        $putResponse = Http::withBody($zip, 'application/zip')
            ->withHeaders(['Content-Type' => 'application/zip'])
            ->put($uploadUrl);

        if ($putResponse->failed()) {
            Log::error('Smile Identity S3 upload failed', [
                'job_id' => $jobId,
                'status' => $putResponse->status(),
                'body'   => Str::limit((string) $putResponse->body(), 500),
            ]);
            throw new RuntimeException('Smile Identity S3 upload failed (HTTP ' . $putResponse->status() . ').');
        }

        return [
            'smile_job_id' => $smileJobId,
            'job_id'       => $jobId,
            'status'       => 'submitted',
            'raw'          => $prep,
        ];
    }

    /**
     * Common envelope for synchronous endpoints (id_verification, aml_check).
     */
    protected function envelope(array $sig, string $userId, string $jobId, int $jobType, array $extra): array
    {
        return array_merge([
            'partner_id'         => $this->partnerId,
            'timestamp'          => $sig['timestamp'],
            'signature'          => $sig['signature'],
            'source_sdk'         => config('smile.sdk.name'),
            'source_sdk_version' => config('smile.sdk.version'),
            'partner_params'     => [
                'user_id'  => $userId,
                'job_id'   => $jobId,
                'job_type' => $jobType,
            ],
        ], $extra);
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout((int) config('smile.http.timeout', 15))
            ->retry(
                (int) config('smile.http.retry', 2),
                (int) config('smile.http.retry_sleep', 500),
                throw: false,
            );
    }

    /**
     * Decode JSON response and throw on a clearly failed HTTP status.
     */
    protected function jsonOrThrow(Response $response): array
    {
        $body = $response->json();
        if (!is_array($body)) {
            throw new RuntimeException('Smile Identity returned non-JSON payload (HTTP ' . $response->status() . ').');
        }
        if ($response->failed()) {
            $code = $body['code'] ?? $response->status();
            $msg  = $body['error'] ?? $body['message'] ?? 'Smile Identity request failed';
            throw new RuntimeException("Smile Identity error [{$code}]: {$msg}");
        }
        return $body;
    }

    /**
     * Normalise a non-throwing JSON response into the {smile_job_id, job_id, status, raw} shape.
     */
    protected function normalize(Response $response, string $jobId): array
    {
        $body = is_array($response->json()) ? $response->json() : [];

        return [
            'smile_job_id' => $body['SmileJobID'] ?? $body['smile_job_id'] ?? null,
            'job_id'       => $jobId,
            'status'       => $response->failed() ? 'failed' : ($body['status'] ?? 'submitted'),
            'raw'          => $body,
        ];
    }

    /**
     * Build an in-memory ZIP archive with the required `info.json` entry.
     * The selfie / document images are already base64-embedded inside info.json
     * (per Smile Identity v1 spec); no separate binary entries needed.
     */
    protected function buildZipPackage(string $infoJson): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'smile_');
        if ($tmp === false) {
            throw new RuntimeException('Unable to create tempfile for Smile Identity ZIP.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Unable to open ZIP archive for Smile Identity submission.');
        }

        $zip->addFromString('info.json', $infoJson);
        $zip->close();

        $contents = file_get_contents($tmp);
        @unlink($tmp);

        if ($contents === false) {
            throw new RuntimeException('Unable to read assembled ZIP for Smile Identity submission.');
        }

        return $contents;
    }
}
