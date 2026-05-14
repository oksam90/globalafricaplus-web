<?php

namespace App\Console\Commands;

use App\Services\SmileIdentity\SmileSignature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * One-shot diagnostic — calls Smile's /v1/token and /v1/upload with the
 * exact partner_id / api_key configured in .env, prints the raw HTTP
 * response and headers. Designed to give Smile support an unambiguous
 * trace when /v1/token returns 2205.
 *
 *   php artisan smile:probe
 *
 * Optional flags:
 *   --product=biometric_kyc   (default doc_verification)
 *   --country=SN
 *   --id-type=DRIVERS_LICENSE
 */
class SmileSandboxProbe extends Command
{
    protected $signature = 'smile:probe
        {--product=doc_verification : Product to request from /v1/token}
        {--country=SN               : ISO country code for /v1/upload}
        {--id-type=DRIVERS_LICENSE  : ID type for /v1/upload}';

    protected $description = 'Probe Smile Identity /token and /upload to diagnose sandbox auth issues.';

    public function handle(): int
    {
        $partner = (string) config('smile.partner_id');
        $apiKey  = (string) config('smile.api_key');
        $base    = (string) config('smile.base_url');

        if ($partner === '' || $apiKey === '') {
            $this->error('SMILE_PARTNER_ID or SMILE_API_KEY is empty in .env. Aborting.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Probing Smile Identity sandbox …');
        $this->table(
            ['key', 'value'],
            [
                ['partner_id',  $partner],
                ['environment', config('smile.environment')],
                ['base_url',    $base],
                ['callback',    config('smile.callback_url')],
            ],
        );

        // ── /v1/token ────────────────────────────────────────────────
        $this->newLine();
        $this->info('▶ POST ' . $base . '/token');

        $sig = SmileSignature::generate();
        $tokenPayload = [
            'user_id'      => 'probe-user-' . Str::random(6),
            'job_id'       => (string) Str::uuid(),
            'product'      => (string) $this->option('product'),
            'partner_id'   => $partner,
            'timestamp'    => $sig['timestamp'],
            'signature'    => $sig['signature'],
            'callback_url' => (string) config('smile.callback_url'),
        ];
        $tokenResponse = Http::acceptJson()->asJson()->post($base . '/token', $tokenPayload);
        $this->dumpResponse($tokenResponse, $tokenPayload);

        // ── /v1/upload ───────────────────────────────────────────────
        $this->newLine();
        $this->info('▶ POST ' . $base . '/upload');

        $sig2 = SmileSignature::generate();
        $uploadPayload = [
            'partner_id'         => $partner,
            'timestamp'          => $sig2['timestamp'],
            'signature'          => $sig2['signature'],
            'source_sdk'         => 'rest_api',
            'source_sdk_version' => '1.0.0',
            'file_name'          => 'submission.zip',
            'smile_client_id'    => $partner,
            'callback_url'       => (string) config('smile.callback_url'),
            'partner_params'     => [
                'user_id'  => 'probe-user-' . Str::random(6),
                'job_id'   => (string) Str::uuid(),
                'job_type' => 6, // Document Verification
            ],
            'model_parameters'   => (object) [],
        ];
        $uploadResponse = Http::acceptJson()->asJson()->post($base . '/upload', $uploadPayload);
        $this->dumpResponse($uploadResponse, $uploadPayload);

        $this->newLine();
        $this->comment('Copy the two ▶ blocks above into your reply to Smile support ticket #1757.');

        return self::SUCCESS;
    }

    protected function dumpResponse(\Illuminate\Http\Client\Response $resp, array $sentPayload): void
    {
        $this->line('  request_payload:');
        $this->line('    ' . json_encode($sentPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->line('  http_status: ' . $resp->status());
        $this->line('  response_headers:');
        foreach ($resp->headers() as $h => $vs) {
            if (in_array(strtolower($h), ['date', 'set-cookie', 'x-amzn-requestid'], true)) continue;
            $this->line('    ' . $h . ': ' . implode(', ', $vs));
        }
        $this->line('  response_body:');
        $body = $resp->json();
        $this->line('    ' . ($body !== null
            ? json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : substr($resp->body(), 0, 1000)));
    }
}
