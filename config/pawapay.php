<?php

/*
|--------------------------------------------------------------------------
| PawaPay — Merchant API v2
|--------------------------------------------------------------------------
|
| PSP mobile money panafricain (20+ marchés, une seule API).
|
| Docs        : https://docs.pawapay.io/v2/docs/how_to_start
| Tarification: https://www.pawapay.io/fees
|
| Authentification : Bearer <API token> (token généré depuis le Dashboard,
| différent entre sandbox et production).
|
| ⚠️ Le Dashboard exige que les Callback URLs soient configurées AVANT de
| pouvoir générer un token API. Utiliser les URLs listées dans
| `callbacks` ci-dessous.
|
*/

// Base publique des callbacks : PAWAPAY_CALLBACK_BASE si renseignée (utile
// pour exposer un tunnel ngrok en local), sinon APP_URL. `?:` et non `??` afin
// qu'une variable présente mais VIDE retombe bien sur APP_URL.
$callbackBase = rtrim(
    env('PAWAPAY_CALLBACK_BASE') ?: env('APP_URL', 'https://globalafricaplus.com'),
    '/',
);

return [
    /*
    |--------------------------------------------------------------------------
    | Environnement & identifiants
    |--------------------------------------------------------------------------
    | mode : "sandbox" | "production"
    */
    'mode'      => env('PAWAPAY_MODE', 'sandbox'),
    'api_token' => env('PAWAPAY_API_TOKEN'),

    'base_url' => env('PAWAPAY_BASE_URL', env('PAWAPAY_MODE', 'sandbox') === 'production'
        ? 'https://api.pawapay.io'
        : 'https://api.sandbox.pawapay.io'),

    'timeout' => (int) env('PAWAPAY_TIMEOUT', 20),

    /*
    |--------------------------------------------------------------------------
    | Callback URLs (à recopier telles quelles dans le Dashboard PawaPay)
    |--------------------------------------------------------------------------
    | Onglet Developers → Callback URLs. En local on réécrit vers APP_URL pour
    | pouvoir tester avec un tunnel (ngrok/expose).
    */
    'callbacks' => [
        'deposits'  => $callbackBase . '/api/v1/webhooks/pawapay/deposits',
        'payouts'   => $callbackBase . '/api/v1/webhooks/pawapay/payouts',
        'refunds'   => $callbackBase . '/api/v1/webhooks/pawapay/refunds',
        'checkouts' => $callbackBase . '/api/v1/webhooks/pawapay/checkouts',
    ],

    /*
    | URLs de retour navigateur (Payment Page).
    */
    'return_url' => rtrim(env('APP_URL', 'https://globalafricaplus.com'), '/') . '/paiement/succes',
    'cancel_url' => rtrim(env('APP_URL', 'https://globalafricaplus.com'), '/') . '/paiement/annule',

    /*
    |--------------------------------------------------------------------------
    | Sécurité des callbacks
    |--------------------------------------------------------------------------
    | Si « Sign all callbacks » est activé dans le Dashboard (onglet API
    | Security), PawaPay signe chaque callback (RFC-9421 : Signature,
    | Signature-Input, Content-Digest). Notre traitement re-vérifie de toute
    | façon le statut auprès de l'API (source de vérité), la signature est donc
    | une couche défensive supplémentaire.
    */
    'signed_callbacks' => (bool) env('PAWAPAY_SIGNED_CALLBACKS', false),
    'public_key'       => env('PAWAPAY_CALLBACK_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Payment Page (checkout hébergé)
    |--------------------------------------------------------------------------
    */
    'payment_page' => [
        'enabled'  => (bool) env('PAWAPAY_PAYMENT_PAGE', true),
        'language' => env('PAWAPAY_LANGUAGE', 'FR'), // EN | FR
        // Libellé affiché sur le téléphone du payeur (4-22 car. alphanum + espaces)
        'statement_description' => env('PAWAPAY_STATEMENT', 'GlobalAfrica Plus'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bornes de décaissement (garde-fou applicatif)
    |--------------------------------------------------------------------------
    | Les vraies bornes par opérateur sont exposées par GET /v2/active-conf ;
    | celles-ci évitent simplement d'envoyer des montants absurdes.
    */
    'disburse' => [
        'enabled' => (bool) env('PAWAPAY_DISBURSE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Marchés couverts — pays, opérateurs, devises et TARIFS
    |--------------------------------------------------------------------------
    | Clé = code ISO-3166 alpha-2 (celui utilisé partout dans l'application).
    |   iso3      : code alpha-3 attendu par l'API PawaPay
    |   currency  : devise de règlement mobile money du marché
    |   prefix    : indicatif téléphonique international
    |   decimals  : nombre de décimales acceptées par les opérateurs du marché
    |   providers : opérateurs mobile money, le PREMIER étant celui par défaut
    |
    | Tarifs (source https://www.pawapay.io/fees, hors taxes) :
    |   collection.percent   → frais de COLLECTE à la charge de l'entreprise
    |   collection.fixed     → part fixe éventuelle (devise du marché)
    |   collection.customer_percent / customer_fixed
    |                        → frais prélevés par l'OPÉRATEUR au payeur
    |                          (informatif : hors de notre transaction)
    |   disbursement.percent → frais de DÉCAISSEMENT à la charge de l'entreprise
    |   disbursement.fixed   → part fixe éventuelle
    |
    | Les barèmes par paliers (Kenya, Tanzanie, Ouganda) sont approximés par
    | leur borne haute afin de ne jamais sous-facturer la plateforme.
    */
    'markets' => [

        'BJ' => ['iso3' => 'BEN', 'currency' => 'XOF', 'prefix' => '229', 'decimals' => 0, 'name' => 'Bénin', 'providers' => [
            'MTN_MOMO_BEN' => ['label' => 'MTN MoMo', 'collection' => ['percent' => 0.022], 'disbursement' => ['percent' => 0.015]],
            'MOOV_BEN'     => ['label' => 'Moov Money', 'collection' => ['percent' => 0.022], 'disbursement' => ['percent' => 0.010]],
        ]],

        'BF' => ['iso3' => 'BFA', 'currency' => 'XOF', 'prefix' => '226', 'decimals' => 0, 'name' => 'Burkina Faso', 'providers' => [
            'MOOV_BFA'   => ['label' => 'Moov Money', 'collection' => ['percent' => 0.030], 'disbursement' => ['percent' => 0.020]],
            // Orange Burkina : collecte uniquement (pas de décaissement PawaPay).
            'ORANGE_BFA' => ['label' => 'Orange Money', 'collection' => ['percent' => 0.033], 'disbursement' => ['percent' => 0.020, 'supported' => false]],
        ]],

        'CM' => ['iso3' => 'CMR', 'currency' => 'XAF', 'prefix' => '237', 'decimals' => 0, 'name' => 'Cameroun', 'providers' => [
            'MTN_MOMO_CMR' => ['label' => 'MTN MoMo', 'collection' => ['percent' => 0.0175], 'disbursement' => ['percent' => 0.013]],
            'ORANGE_CMR'   => ['label' => 'Orange Money', 'collection' => ['percent' => 0.0177], 'disbursement' => ['percent' => 0.010]],
        ]],

        'CI' => ['iso3' => 'CIV', 'currency' => 'XOF', 'prefix' => '225', 'decimals' => 0, 'name' => 'Côte d\'Ivoire', 'providers' => [
            'MTN_MOMO_CIV' => ['label' => 'MTN MoMo', 'collection' => ['percent' => 0.018], 'disbursement' => ['percent' => 0.013]],
            'ORANGE_CIV'   => ['label' => 'Orange Money', 'collection' => ['percent' => 0.025], 'disbursement' => ['percent' => 0.020]],
            'WAVE_CIV'     => ['label' => 'Wave', 'collection' => ['percent' => 0.020], 'disbursement' => ['percent' => 0.020]],
            // Actif sur le compte marchand mais absent de la grille publique :
            // on retient le tarif le plus élevé du marché (Orange CI) pour ne
            // jamais sous-facturer.
            'MOOV_CIV'     => ['label' => 'Moov Money', 'collection' => ['percent' => 0.025], 'disbursement' => ['percent' => 0.020]],
        ]],

        'CG' => ['iso3' => 'COG', 'currency' => 'XAF', 'prefix' => '242', 'decimals' => 0, 'name' => 'Congo-Brazzaville', 'providers' => [
            'AIRTEL_COG'   => ['label' => 'Airtel Money', 'collection' => ['percent' => 0.040], 'disbursement' => ['percent' => 0.010]],
            'MTN_MOMO_COG' => ['label' => 'MTN MoMo', 'collection' => ['percent' => 0.040], 'disbursement' => ['percent' => 0.010]],
        ]],

        'CD' => ['iso3' => 'COD', 'currency' => 'CDF', 'prefix' => '243', 'decimals' => 2, 'name' => 'RD Congo', 'providers' => [
            'VODACOM_MPESA_COD' => ['label' => 'M-Pesa', 'collection' => ['percent' => 0.025], 'disbursement' => ['percent' => 0.020]],
            'AIRTEL_COD'        => ['label' => 'Airtel Money', 'collection' => ['percent' => 0.030], 'disbursement' => ['percent' => 0.020]],
            'ORANGE_COD'        => ['label' => 'Orange Money', 'collection' => ['percent' => 0.030], 'disbursement' => ['percent' => 0.010]],
        ]],

        'ET' => ['iso3' => 'ETH', 'currency' => 'ETB', 'prefix' => '251', 'decimals' => 2, 'name' => 'Éthiopie', 'providers' => [
            'MPESA_ETH' => ['label' => 'Safaricom M-Pesa', 'collection' => ['percent' => 0.015], 'disbursement' => ['percent' => 0.015]],
        ]],

        'GA' => ['iso3' => 'GAB', 'currency' => 'XAF', 'prefix' => '241', 'decimals' => 0, 'name' => 'Gabon', 'providers' => [
            'AIRTEL_GAB' => [
                'label'        => 'Airtel Money',
                'collection'   => ['percent' => 0.020, 'customer_percent' => 0.010],
                'disbursement' => ['percent' => 0.010],
            ],
        ]],

        'GH' => ['iso3' => 'GHA', 'currency' => 'GHS', 'prefix' => '233', 'decimals' => 2, 'name' => 'Ghana', 'providers' => [
            'MTN_MOMO_GHA'   => ['label' => 'MTN MoMo', 'collection' => ['percent' => 0.020], 'disbursement' => ['percent' => 0.010]],
            'AIRTELTIGO_GHA' => ['label' => 'AT (AirtelTigo)', 'collection' => ['percent' => 0.020], 'disbursement' => ['percent' => 0.010]],
            'VODAFONE_GHA'   => ['label' => 'Telecel', 'collection' => ['percent' => 0.020], 'disbursement' => ['percent' => 0.010]],
        ]],

        'KE' => ['iso3' => 'KEN', 'currency' => 'KES', 'prefix' => '254', 'decimals' => 2, 'name' => 'Kenya', 'providers' => [
            // Barème par paliers : approximé par la borne haute (108 / 13 KES).
            'MPESA_KEN' => ['label' => 'M-PESA', 'collection' => ['percent' => 0.010, 'fixed' => 108], 'disbursement' => ['percent' => 0.010, 'fixed' => 13]],
        ]],

        'LS' => ['iso3' => 'LSO', 'currency' => 'LSL', 'prefix' => '266', 'decimals' => 2, 'name' => 'Lesotho', 'providers' => [
            'MPESA_LSO' => ['label' => 'M-Pesa', 'collection' => ['percent' => 0.020], 'disbursement' => ['percent' => 0.020]],
        ]],

        'MW' => ['iso3' => 'MWI', 'currency' => 'MWK', 'prefix' => '265', 'decimals' => 2, 'name' => 'Malawi', 'providers' => [
            'AIRTEL_MWI' => ['label' => 'Airtel Money', 'collection' => ['percent' => 0.0333], 'disbursement' => ['percent' => 0.027625]],
            'TNM_MWI'    => ['label' => 'TNM Mpamba', 'collection' => ['percent' => 0.0333, 'customer_fixed' => 300], 'disbursement' => ['percent' => 0.0275]],
        ]],

        'MZ' => ['iso3' => 'MOZ', 'currency' => 'MZN', 'prefix' => '258', 'decimals' => 2, 'name' => 'Mozambique', 'providers' => [
            'VODACOM_MOZ' => ['label' => 'M-Pesa', 'collection' => ['percent' => 0.0457], 'disbursement' => ['percent' => 0.020]],
            'MOVITEL_MOZ' => ['label' => 'Movitel e-Mola', 'collection' => ['percent' => 0.040], 'disbursement' => ['percent' => 0.010]],
        ]],

        'NG' => ['iso3' => 'NGA', 'currency' => 'NGN', 'prefix' => '234', 'decimals' => 2, 'name' => 'Nigeria', 'providers' => [
            'MTN_MOMO_NGA' => ['label' => 'MTN MoMo', 'collection' => ['percent' => 0.030], 'disbursement' => ['percent' => 0.010, 'fixed' => 10]],
            'AIRTEL_NGA'   => ['label' => 'Airtel Money', 'collection' => ['percent' => 0.030], 'disbursement' => ['percent' => 0.010]],
        ]],

        'RW' => ['iso3' => 'RWA', 'currency' => 'RWF', 'prefix' => '250', 'decimals' => 0, 'name' => 'Rwanda', 'providers' => [
            'MTN_MOMO_RWA' => ['label' => 'MTN MoMo', 'collection' => ['percent' => 0.031], 'disbursement' => ['percent' => 0.010, 'fixed' => 60]],
            'AIRTEL_RWA'   => ['label' => 'Airtel Money', 'collection' => ['percent' => 0.025], 'disbursement' => ['percent' => 0.010]],
        ]],

        'SN' => ['iso3' => 'SEN', 'currency' => 'XOF', 'prefix' => '221', 'decimals' => 0, 'name' => 'Sénégal', 'providers' => [
            'ORANGE_SEN' => ['label' => 'Orange Money', 'collection' => ['percent' => 0.020], 'disbursement' => ['percent' => 0.018]],
            'FREE_SEN'   => ['label' => 'YAS (Free Money)', 'collection' => ['percent' => 0.020], 'disbursement' => ['percent' => 0.015]],
            'WAVE_SEN'   => ['label' => 'Wave', 'collection' => ['percent' => 0.020], 'disbursement' => ['percent' => 0.020]],
        ]],

        'SL' => ['iso3' => 'SLE', 'currency' => 'SLE', 'prefix' => '232', 'decimals' => 2, 'name' => 'Sierra Leone', 'providers' => [
            'ORANGE_SLE' => ['label' => 'Orange Money', 'collection' => ['percent' => 0.033], 'disbursement' => ['percent' => 0.0215]],
        ]],

        'TZ' => ['iso3' => 'TZA', 'currency' => 'TZS', 'prefix' => '255', 'decimals' => 2, 'name' => 'Tanzanie', 'providers' => [
            'VODACOM_TZA' => ['label' => 'M-Pesa', 'collection' => ['percent' => 0.010], 'disbursement' => ['percent' => 0.010, 'fixed' => 200]],
            'AIRTEL_TZA'  => ['label' => 'Airtel Money', 'collection' => ['percent' => 0.0218], 'disbursement' => ['percent' => 0.010, 'fixed' => 200]],
            'TIGO_TZA'    => ['label' => 'YAS (Tigo Pesa)', 'collection' => ['percent' => 0.010], 'disbursement' => ['percent' => 0.010]],
            'HALOTEL_TZA' => ['label' => 'Halopesa', 'collection' => ['percent' => 0.020], 'disbursement' => ['percent' => 0.010, 'fixed' => 300]],
        ]],

        'UG' => ['iso3' => 'UGA', 'currency' => 'UGX', 'prefix' => '256', 'decimals' => 0, 'name' => 'Ouganda', 'providers' => [
            'MTN_MOMO_UGA'    => ['label' => 'MTN MoMo', 'collection' => ['percent' => 0.030], 'disbursement' => ['percent' => 0.010, 'fixed' => 1200]],
            'AIRTEL_OAPI_UGA' => ['label' => 'Airtel Money', 'collection' => ['percent' => 0.025], 'disbursement' => ['percent' => 0.010, 'fixed' => 1000]],
        ]],

        'ZM' => ['iso3' => 'ZMB', 'currency' => 'ZMW', 'prefix' => '260', 'decimals' => 2, 'name' => 'Zambie', 'providers' => [
            'MTN_MOMO_ZMB'    => ['label' => 'MTN MoMo', 'collection' => ['percent' => 0.030], 'disbursement' => ['percent' => 0.020]],
            'AIRTEL_OAPI_ZMB' => ['label' => 'Airtel Money', 'collection' => ['percent' => 0.030], 'disbursement' => ['percent' => 0.020]],
            'ZAMTEL_ZMB'      => ['label' => 'Zamtel Kwacha', 'collection' => ['percent' => 0.030], 'disbursement' => ['percent' => 0.020]],
        ]],
    ],

    /*
    |--------------------------------------------------------------------------
    | Marché par défaut si le pays du projet n'est pas couvert
    |--------------------------------------------------------------------------
    */
    'default_market' => env('PAWAPAY_DEFAULT_MARKET', 'SN'),

    /*
    | Tarif de repli si un opérateur n'a pas de barème renseigné.
    */
    'default_fees' => [
        'collection'   => ['percent' => 0.030, 'fixed' => 0.0],
        'disbursement' => ['percent' => 0.020, 'fixed' => 0.0],
    ],
];
