<?php

/*
|--------------------------------------------------------------------------
| Payments — configuration transverse (PSP + barème de commission)
|--------------------------------------------------------------------------
|
| Deux moyens de paiement coexistent, au choix de l'utilisateur :
|
|   • mobile_money → PawaPay  (config/pawapay.php)
|   • card         → PayDunya (config/paydunya.php) — carte bancaire Visa/Mastercard
|
| Le même choix est proposé pour les investissements, les abonnements et les
| achats de formations.
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Moyens de paiement proposés à l'utilisateur
    |--------------------------------------------------------------------------
    | `gateway`  : PSP qui traite l'opération
    | `payout`   : canal de réception du porteur de projet associé
    | `enabled`  : permet de masquer un moyen sans toucher au code
    */
    'methods' => [
        'mobile_money' => [
            'gateway'     => 'pawapay',
            'label'       => 'Mobile Money',
            'description' => 'Orange Money, MTN, Wave, Airtel, M-Pesa…',
            'payout'      => 'mobile_money',
            'icon'        => '📱',
            'enabled'     => (bool) env('PAYMENT_METHOD_MOBILE_MONEY', true),
        ],
        'card' => [
            'gateway'     => 'paydunya',
            'label'       => 'Carte bancaire',
            'description' => 'Visa / Mastercard — versement sur le compte bancaire du porteur',
            'payout'      => 'bank',
            'icon'        => '💳',
            'enabled'     => (bool) env('PAYMENT_METHOD_CARD', true),
        ],
    ],

    'default_method' => env('PAYMENT_DEFAULT_METHOD', 'mobile_money'),

    /*
    |--------------------------------------------------------------------------
    | PSP par défaut (compatibilité — utilisé quand aucun moyen n'est précisé)
    |--------------------------------------------------------------------------
    | Valeurs supportées : "pawapay", "paydunya".
    */
    'default_gateway' => env('PAYMENT_GATEWAY', 'pawapay'),

    /*
    | Utilisé si le pays n'est pas couvert par le PSP par défaut.
    */
    'fallback_gateway' => env('PAYMENT_FALLBACK_GATEWAY', 'paydunya'),

    /*
    |--------------------------------------------------------------------------
    | Barème de commission GlobalAfrica+ (revenu plateforme)
    |--------------------------------------------------------------------------
    | Barème dégressif appliqué sur le « Montant Reçu » (ce que le porteur de
    | projet touche réellement), pivots exprimés en EUR :
    |
    |   Montant de l'investissement ≥      5 €  →  3,00 %
    |   Montant de l'investissement ≥  5 000 €  →  2,00 %
    |   Montant de l'investissement ≥ 20 000 €  →  1,00 %
    |
    | Taux retenu pour le montant moyen : 3,00 %.
    |
    | `display_thresholds = false` : l'interface publique annonce « 3 % / 2 % /
    | 1 % selon le montant » SANS révéler les montants pivots.
    */
    'commission' => [
        // Devise dans laquelle les pivots ci-dessous sont exprimés.
        'pivot_currency' => 'EUR',

        // Investissement minimum accepté (borne basse du barème).
        'min_amount' => (float) env('COMMISSION_MIN_AMOUNT', 5),

        // `max` = borne HAUTE exclusive de la tranche ; null = dernière tranche.
        'tiers' => [
            ['max' => (float) env('COMMISSION_PIVOT_2', 5000),  'rate' => (float) env('COMMISSION_RATE_1', 0.03)],
            ['max' => (float) env('COMMISSION_PIVOT_3', 20000), 'rate' => (float) env('COMMISSION_RATE_2', 0.02)],
            ['max' => null,                                     'rate' => (float) env('COMMISSION_RATE_3', 0.01)],
        ],

        // Ne jamais afficher les pivots côté public.
        'display_thresholds' => false,

        // Libellé public du barème.
        'public_label' => 'Commission GlobalAfrica+ (barème dégressif 3 % / 2 % / 1 % selon le montant)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Qui supporte les frais ?
    |--------------------------------------------------------------------------
    | Règles métier validées :
    |  - investment        : l'INVESTISSEUR supporte 100 % des frais PSP + la
    |                        commission plateforme (majoration du montant envoyé).
    |  - refund_no_payout  : non-décaissement par le porteur → frais de
    |                        remboursement à la charge de la PLATEFORME.
    |  - refund_dispute    : litige après perception (partielle ou totale) →
    |                        frais de remboursement à la charge du PORTEUR.
    */
    'fees_borne_by' => [
        'investment'       => 'investor',
        'refund_no_payout' => 'platform',
        'refund_dispute'   => 'project_owner',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pays couverts par PayDunya mais absents des marchés PawaPay
    |--------------------------------------------------------------------------
    | Complète `pawapay.markets` pour la devise et l'indicatif. Sert uniquement
    | au calcul des frais et à l'affichage : aucun décaissement mobile money
    | PawaPay n'y est possible.
    */
    'extra_markets' => [
        'ML' => ['currency' => 'XOF', 'prefix' => '223', 'decimals' => 0, 'name' => 'Mali'],
        'NE' => ['currency' => 'XOF', 'prefix' => '227', 'decimals' => 0, 'name' => 'Niger'],
        'GW' => ['currency' => 'XOF', 'prefix' => '245', 'decimals' => 0, 'name' => 'Guinée-Bissau'],
        'TD' => ['currency' => 'XAF', 'prefix' => '235', 'decimals' => 0, 'name' => 'Tchad'],
        'CF' => ['currency' => 'XAF', 'prefix' => '236', 'decimals' => 0, 'name' => 'Centrafrique'],
        'GQ' => ['currency' => 'XAF', 'prefix' => '240', 'decimals' => 0, 'name' => 'Guinée équatoriale'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Assiette des frais de collecte
    |--------------------------------------------------------------------------
    | true  : les frais de collecte sont calculés sur le pays de l'INVESTISSEUR
    | false : sur le pays du PROJET (règle actuelle — cf. spécification métier)
    */
    'use_payer_country_for_collection' => env('FEES_USE_PAYER_COUNTRY', false),
];
