<?php

/*
|--------------------------------------------------------------------------
| PayDunya Configuration
|--------------------------------------------------------------------------
|
| Configuration for the PayDunya payment gateway integration.
| PayDunya is the primary gateway for UEMOA countries (XOF) and CEMAC (XAF).
|
| Documentation: https://developers.paydunya.com
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    */
    'master_key'  => env('PAYDUNYA_MASTER_KEY'),
    'private_key' => env('PAYDUNYA_PRIVATE_KEY'),
    'public_key'  => env('PAYDUNYA_PUBLIC_KEY'),
    'token'       => env('PAYDUNYA_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Environment Mode
    |--------------------------------------------------------------------------
    | Supported: "test", "live"
    */
    'mode' => env('PAYDUNYA_MODE', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Store Information
    |--------------------------------------------------------------------------
    */
    'store' => [
        'name'         => env('PAYDUNYA_STORE_NAME', 'Globalafrica+'),
        'tagline'      => 'Plateforme panafricaine d\'investissement, mentorat et formation',
        'phone'        => env('PAYDUNYA_STORE_PHONE', '+221000000000'),
        'postal_address' => env('PAYDUNYA_STORE_ADDRESS', 'Dakar, Sénégal'),
        'website_url'  => env('PAYDUNYA_WEBSITE', 'https://globalafricaplus.com'),
        'logo_url'     => env('PAYDUNYA_LOGO_URL', 'https://globalafricaplus.com/logo.png'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback URLs
    |--------------------------------------------------------------------------
    | In local/dev environments we auto-rewrite to APP_URL so that PayDunya
    | redirects the user back to the dev server (127.0.0.1:8000) instead of
    | the production domain. In production the hardcoded env values are used.
    */
    'callback_url' => env('APP_ENV') === 'local'
        ? rtrim(env('APP_URL', 'http://127.0.0.1:8000'), '/') . '/api/v1/webhooks/paydunya'
        : env('PAYDUNYA_CALLBACK_URL'),
    'return_url'   => env('APP_ENV') === 'local'
        ? rtrim(env('APP_URL', 'http://127.0.0.1:8000'), '/') . '/paiement/succes'
        : env('PAYDUNYA_RETURN_URL'),
    'cancel_url'   => env('APP_ENV') === 'local'
        ? rtrim(env('APP_URL', 'http://127.0.0.1:8000'), '/') . '/paiement/annule'
        : env('PAYDUNYA_CANCEL_URL'),

    /*
    |--------------------------------------------------------------------------
    | Commission Rates (Globalafrica+ platform fees)
    |--------------------------------------------------------------------------
    | Applied on top of PayDunya transaction fees.
    | - tier1: small transactions (< 100k XOF equivalent)
    | - tier2: medium transactions (100k – 1M XOF)
    | - tier3: large transactions (> 1M XOF)
    */
    'commission_rates' => [
        'tier1' => 0.03, // 3%
        'tier2' => 0.02, // 2%
        'tier3' => 0.01, // 1%
    ],

    'tier_thresholds' => [
        'tier1_max' => 100_000,   // XOF
        'tier2_max' => 1_000_000, // XOF
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Payment Channels
    |--------------------------------------------------------------------------
    | Maps PayDunya channel keys to human labels. Used for checkout UI and
    | analytics. Match the keys PayDunya accepts in `custom_data.payment_method`.
    */
    'channels' => [
        'card'                 => 'Carte bancaire (Visa/Mastercard)',
        'orange-money-senegal' => 'Orange Money Sénégal',
        'free-money-senegal'   => 'Free Money Sénégal',
        'wave-senegal'         => 'Wave Sénégal',
        'moov-benin'           => 'Moov Bénin',
        'mtn-benin'            => 'MTN Bénin',
        'orange-money-burkina' => 'Orange Money Burkina',
        'moov-burkina-faso'    => 'Moov Burkina Faso',
        'orange-money-ci'      => 'Orange Money Côte d\'Ivoire',
        'mtn-ci'               => 'MTN Côte d\'Ivoire',
        'moov-ci'              => 'Moov Côte d\'Ivoire',
        'wave-ci'              => 'Wave Côte d\'Ivoire',
        'orange-money-mali'    => 'Orange Money Mali',
        't-money-togo'         => 'T-Money Togo',
        'moov-togo'            => 'Moov Togo',
        'mtn-cameroon'         => 'MTN Cameroun',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    */
    'currencies' => ['XOF', 'XAF', 'EUR', 'USD'],

    /*
    |--------------------------------------------------------------------------
    | Country Routing — UEMOA / CEMAC zones
    |--------------------------------------------------------------------------
    | ISO-3166 alpha-2 country codes handled by PayDunya as primary gateway.
    */
    'uemoa_countries'  => ['SN', 'CI', 'ML', 'BF', 'TG', 'BJ', 'NE', 'GW'],
    'cemac_countries'  => ['CM', 'CF', 'TD', 'CG', 'GQ', 'GA'],

    /*
    |--------------------------------------------------------------------------
    | Webhook Security
    |--------------------------------------------------------------------------
    | Shared secret used to verify HMAC-SHA512 signatures on IPN webhooks.
    */
    'webhook_secret' => env('PAYDUNYA_WEBHOOK_SECRET', env('PAYDUNYA_MASTER_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Disbursement (Payout) Settings
    |--------------------------------------------------------------------------
    */
    'disburse' => [
        'enabled'          => env('PAYDUNYA_DISBURSE_ENABLED', true),
        'min_amount_xof'   => 500,
        'max_amount_xof'   => 5_000_000,
        'auto_refund_days' => 90, // Escrow auto-refund window
    ],
];
