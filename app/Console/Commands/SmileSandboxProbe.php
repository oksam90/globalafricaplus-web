<?php

namespace App\Console\Commands;

use App\Services\SmileIdentity\SmileSignature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Diagnostic command — hammers Smile's sandbox with four parallel probes
 * so we can isolate the cause of 2205 ("not authorized"):
 *
 *   1. /v1/id_verification (job_type 5, Basic KYC)   — known-good control
 *   2. /v1/token product=basic_kyc                    — same product, hosted SDK
 *   3. /v1/token product=doc_verification             — failing target
 *   4. /v1/upload job_type=6 (Document Verification)  — failing target
 *
 * If 1+2 succeed and 3+4 fail with the *same* signature scheme + key, the
 * problem is an account-level product gate at Smile, not our code.
 */
class SmileSandboxProbe extends Command
{
    protected $signature = 'smile:probe';
    protected $description = 'Probe Smile Identity sandbox endpoints to isolate auth vs. product-gate issues.';

    protected string $partner = '';
    protected string $base    = '';

    public function handle(): int
    {
        $this->partner = (string) config('smile.partner_id');
        $this->base    = (string) config('smile.base_url');
        $apiKey        = (string) config('smile.api_key');

        if ($this->partner === '' || $apiKey === '') {
            $this->error('SMILE_PARTNER_ID or SMILE_API_KEY is empty in .env. Aborting.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Probing Smile Identity sandbox …');
        $this->table(['key', 'value'], [
            ['partner_id',  $this->partner],
            ['environment', config('smile.environment')],
            ['base_url',    $this->base],
            ['callback',    config('smile.callback_url')],
            ['api_key_hint', substr($apiKey, 0, 8) . '…' . substr($apiKey, -4)],
        ]);

        // ── 1. CONTROL: /v1/id_verification (Basic KYC) ─────────────────
        $this->newLine();
        $this->info('▶ [1] CONTROL POST ' . $this->base . '/id_verification  (job_type 5)');
        $r1 = $this->probeIdVerification();
        $this->dumpResponse($r1);

        // ── 2. /v1/token with product=basic_kyc ─────────────────────────
        $this->newLine();
        $this->info('▶ [2] POST ' . $this->base . '/token  (product=basic_kyc)');
        $r2 = $this->probeToken('basic_kyc');
        $this->dumpResponse($r2);

        // ── 3. /v1/token with product=doc_verification ──────────────────
        $this->newLine();
        $this->info('▶ [3] POST ' . $this->base . '/token  (product=doc_verification)');
        $r3 = $this->probeToken('doc_verification');
        $this->dumpResponse($r3);

        // ── 4. /v1/upload with job_type 6 (Document Verification) ───────
        $this->newLine();
        $this->info('▶ [4] POST ' . $this->base . '/upload  (job_type 6)');
        $r4 = $this->probeUpload(6);
        $this->dumpResponse($r4);

        // ── Diagnosis ───────────────────────────────────────────────────
        $this->newLine();
        $this->line('═══ DIAGNOSIS ═══');
        $row = fn (string $label, Response $r) => [$label, $r->status(), ($r->json()['code'] ?? '—')];
        $this->table(
            ['probe',                                 'http', 'smile_code'],
            [
                $row('1. /id_verification (control)', $r1['response']),
                $row('2. /token  basic_kyc',          $r2['response']),
                $row('3. /token  doc_verification',   $r3['response']),
                $row('4. /upload job_type=6',         $r4['response']),
            ],
        );

        $this->newLine();
        if ($r1['response']->ok() && (!$r3['response']->ok() || !$r4['response']->ok())) {
            $this->warn('→ Signature scheme proven correct (probe 1 succeeds).');
            $this->warn('→ Probe(s) 3/4 failing on the same key + scheme proves an account-level');
            $this->warn('  product gate at Smile, NOT a request-format bug on our side.');
        }

        $this->newLine();
        $this->comment('Copy the four ▶ blocks above into your reply to Smile support ticket #1757.');

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────

    protected function probeIdVerification(): array
    {
        $sig = SmileSignature::generate();
        $payload = [
            'partner_id'         => $this->partner,
            'timestamp'          => $sig['timestamp'],
            'signature'          => $sig['signature'],
            'source_sdk'         => 'rest_api',
            'source_sdk_version' => '1.0.0',
            'partner_params'     => [
                'user_id'  => 'probe-' . Str::random(6),
                'job_id'   => (string) Str::uuid(),
                'job_type' => 5,
            ],
            'country'      => 'SN',
            'id_type'      => 'NATIONAL_ID',
            'id_number'    => '00000000000', // sandbox: trailing 0 = approved
            'first_name'   => 'Aminata',
            'last_name'    => 'Diop',
            'dob'          => '1990-05-15',
            'callback_url' => (string) config('smile.callback_url'),
        ];
        return [
            'payload'  => $payload,
            'response' => Http::acceptJson()->asJson()->post($this->base . '/id_verification', $payload),
        ];
    }

    protected function probeToken(string $product): array
    {
        $sig = SmileSignature::generate();
        $payload = [
            'user_id'      => 'probe-' . Str::random(6),
            'job_id'       => (string) Str::uuid(),
            'product'      => $product,
            'partner_id'   => $this->partner,
            'timestamp'    => $sig['timestamp'],
            'signature'    => $sig['signature'],
            'callback_url' => (string) config('smile.callback_url'),
        ];
        return [
            'payload'  => $payload,
            'response' => Http::acceptJson()->asJson()->post($this->base . '/token', $payload),
        ];
    }

    protected function probeUpload(int $jobType): array
    {
        $sig = SmileSignature::generate();
        $payload = [
            'partner_id'         => $this->partner,
            'timestamp'          => $sig['timestamp'],
            'signature'          => $sig['signature'],
            'source_sdk'         => 'rest_api',
            'source_sdk_version' => '1.0.0',
            'file_name'          => 'submission.zip',
            'smile_client_id'    => $this->partner,
            'callback_url'       => (string) config('smile.callback_url'),
            'partner_params'     => [
                'user_id'  => 'probe-' . Str::random(6),
                'job_id'   => (string) Str::uuid(),
                'job_type' => $jobType,
            ],
            'model_parameters'   => (object) [],
        ];
        return [
            'payload'  => $payload,
            'response' => Http::acceptJson()->asJson()->post($this->base . '/upload', $payload),
        ];
    }

    protected function dumpResponse(array $result): void
    {
        /** @var Response $resp */
        $resp    = $result['response'];
        $payload = $result['payload'];

        $this->line('  request_payload:');
        $this->line('    ' . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->line('  http_status: ' . $resp->status());
        $this->line('  response_body:');
        $body = $resp->json();
        $this->line('    ' . ($body !== null
            ? json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : substr($resp->body(), 0, 1000)));
    }
}
