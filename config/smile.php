<?php

/*
|--------------------------------------------------------------------------
| Smile Identity (eKYC + AML)
|--------------------------------------------------------------------------
|
| Configuration for the Smile Identity REST API integration. Replaces the
| former IDnorm provider. See spec § 3.2.
|
| Sandbox  : https://testapi.smileidentity.com/v1
| Production: https://api.smileidentity.com/v1
|
*/

return [

    'partner_id'   => env('SMILE_PARTNER_ID'),
    'api_key'      => env('SMILE_API_KEY'),
    'environment'  => env('SMILE_ENVIRONMENT', 'sandbox'),
    'callback_url' => env('SMILE_CALLBACK_URL'),

    'base_url' => env('SMILE_ENVIRONMENT') === 'production'
        ? 'https://api.smileidentity.com/v1'
        : 'https://testapi.smileidentity.com/v1',

    /*
    |--------------------------------------------------------------------------
    | KYC tier capabilities
    |--------------------------------------------------------------------------
    | Drives the RequireKYCLevel middleware and the per-action daily caps.
    | Amounts in EUR; XOF/XAF amounts are converted via CurrencyService.
    */
    'kyc_levels' => [
        'basic' => [
            'max_daily_eur' => 0,
            'features'      => ['browse', 'apply', 'message'],
        ],
        'verified' => [
            'max_daily_eur' => 5_000,
            'features'      => ['invest', 'subscribe', 'mentor'],
        ],
        'certified' => [
            'max_daily_eur' => 50_000,
            'features'      => ['escrow', 'gov_api', 'high_value'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | KYC validity period
    |--------------------------------------------------------------------------
    | Per UEMOA Directive 02/2015 a successful verification is valid 24 months.
    | After this window the user must re-verify before resuming high-value ops.
    */
    'kyc_expiry_months' => 24,

    /*
    |--------------------------------------------------------------------------
    | AML screening defaults
    |--------------------------------------------------------------------------
    */
    'aml' => [
        'strict_match'        => true,
        'check_pep'           => true,
        'check_sanctions'     => true,
        'check_adverse_media' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP client tuning
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout'     => env('SMILE_HTTP_TIMEOUT', 15),
        'retry'       => env('SMILE_HTTP_RETRY', 2),
        'retry_sleep' => env('SMILE_HTTP_RETRY_SLEEP', 500), // ms
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook replay window (audit fix 2026-05)
    |--------------------------------------------------------------------------
    | The signature alone proves authenticity but not freshness — an attacker
    | who captures a valid callback can replay it later. We reject any
    | timestamp older than `replay_window_seconds` (default 300s = 5 min) and
    | any timestamp dated more than `clock_skew_seconds` in the future
    | (allows a small clock drift between Smile and our server).
    */
    'webhook' => [
        'replay_window_seconds' => env('SMILE_WEBHOOK_REPLAY_WINDOW', 300),
        'clock_skew_seconds'    => env('SMILE_WEBHOOK_CLOCK_SKEW', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | SDK identification (sent in every payload)
    |--------------------------------------------------------------------------
    */
    'sdk' => [
        'name'    => 'rest_api',
        'version' => '1.0.0',
    ],

];
