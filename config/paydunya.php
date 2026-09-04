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
    | Tarification PayDunya
    |--------------------------------------------------------------------------
    | Source : https://paydunya.com/service-fees — « NOUVEAUX FRAIS PAYDUNYA
    | – Standard » (grille d'août 2026). Frais exprimés en % du montant de la
    | transaction.
    |
    | ⚠️ La tranche dépend de NOTRE flux MENSUEL en FCFA (pas du montant de la
    | transaction) :
    |   tier1 : 200 – 99 999 999 FCFA / mois
    |   tier2 : 100 000 000 – 500 000 000
    |   tier3 : + de 500 000 000
    | Un compte qui démarre est en tier1.
    */
    'volume_tier' => (int) env('PAYDUNYA_VOLUME_TIER', 1),

    'fees' => [
        // Carte bancaire Visa/Mastercard — taux unique, tous pays.
        'card' => [
            'percent' => (float) env('PAYDUNYA_CARD_FEE', 0.035), // 3,50 %
            'fixed'   => 0.0,
        ],

        // Encaissement mobile money (PayIn), par pays et par tranche de flux.
        'payin' => [
            'BJ' => [0.0200, 0.0185, 0.0180], // Moov Africa, MTN, Celtiis
            'SN' => [0.0225, 0.0220, 0.0215], // Orange Money, Mixx by Yas, Wizall, Wave
            'BF' => [0.0225, 0.0220, 0.0215], // Orange Money, Moov Africa
            'CI' => [0.0225, 0.0220, 0.0215], // Orange Money, Moov Africa, Wave, MTN
            'TG' => [0.0225, 0.0220, 0.0215], // Mixx by Yas, Moov Africa
            'CM' => [0.0200, 0.0175, 0.0150], // MTN
            'ML' => [0.0225, 0.0220, 0.0215], // non publié pour PayIn — aligné UEMOA
        ],

        // Décaissement mobile money (PayOut), par pays et par tranche de flux.
        'payout' => [
            'BJ' => [0.0150, 0.0125, 0.0180],
            'BF' => [0.0200, 0.0160, 0.0150],
            'CI' => [0.0200, 0.0160, 0.0150],
            'TG' => [0.0200, 0.0160, 0.0150],
            'ML' => [0.0200, 0.0180, 0.0150],
            'CM' => [0.0175, 0.0160, 0.0140],
            'SN' => [0.0200, 0.0195, 0.0190], // Orange Money, Mixx by Yas, Wizall, Wave
        ],

        // Repli pour un pays non listé dans la grille.
        'default_payin'  => [0.0225, 0.0220, 0.0215],
        'default_payout' => [0.0200, 0.0180, 0.0150],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Payment Channels
    |--------------------------------------------------------------------------
    | Liste officielle « Opérateurs Mobile Money Disponibles »
    | (https://developers.paydunya.com/doc/FR/introduction).
    | Ces clés sont celles acceptées par `CheckoutInvoice::addChannel()`.
    */
    'channels' => [
        'card'                 => 'Carte bancaire (Visa/Mastercard)',
        'orange-money-senegal' => 'Orange Money Sénégal',
        'wave-senegal'         => 'Wave Sénégal',
        'free-money-senegal'   => 'Free Money Sénégal',
        'expresso-sn'          => 'Expresso Sénégal',
        'wizall-senegal'       => 'Wizall Sénégal',
        'djamo-sn'             => 'Djamo Sénégal',
        'mtn-benin'            => 'MTN Bénin',
        'moov-benin'           => 'Moov Bénin',
        'celtiis-cash'         => 'Celtiis Cash Bénin',
        'orange-money-ci'      => 'Orange Money Côte d\'Ivoire',
        'wave-ci'              => 'Wave Côte d\'Ivoire',
        'mtn-ci'               => 'MTN Côte d\'Ivoire',
        'moov-ci'              => 'Moov Côte d\'Ivoire',
        'djamo-ci'             => 'Djamo Côte d\'Ivoire',
        't-money-togo'         => 'T-Money Togo',
        'moov-togo'            => 'Moov Togo',
        'orange-money-mali'    => 'Orange Money Mali',
        'moov-ml'              => 'Moov Mali',
        'orange-money-burkina' => 'Orange Money Burkina',
        'moov-burkina-faso'    => 'Moov Burkina Faso',
        'mtn-cameroun'         => 'MTN Cameroun',
    ],

    /*
    | Canaux proposés quand l'utilisateur choisit « Carte bancaire ».
    */
    'card_channels' => ['card'],

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
