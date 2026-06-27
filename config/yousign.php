<?php

/*
|--------------------------------------------------------------------------
| Yousign — signature électronique des conventions (étape 6)
|--------------------------------------------------------------------------
|
| Flux (API v3) :
|   1. POST /signature_requests                  → crée la demande (draft)
|   2. POST /signature_requests/{id}/documents   → téléverse le PDF
|   3. POST /signature_requests/{id}/signers     → ajoute chaque signataire + champ de signature
|   4. POST /signature_requests/{id}/activate    → envoie les emails de signature
|   5. webhook / polling                         → récupère le PDF signé
|
| La clé API vit dans .env (jamais committée).
|
*/

$mode = env('YOUSIGN_MODE', 'sandbox');

return [
    'mode'    => $mode,
    'api_key' => env('YOUSIGN_API_KEY'),

    'base_url' => $mode === 'production'
        ? 'https://api.yousign.app/v3'
        : 'https://api-sandbox.yousign.app/v3',

    // Secret du webhook Yousign (HMAC-SHA256). Optionnel en sandbox.
    'webhook_secret' => env('YOUSIGN_WEBHOOK_SECRET'),

    // Niveau de signature : electronic_signature (simple) |
    // advanced_electronic_signature | qualified_electronic_signature
    'signature_level' => env('YOUSIGN_SIGNATURE_LEVEL', 'electronic_signature'),

    // Authentification du signataire : no_otp | otp_email | otp_sms
    // (no_otp = pas de code à saisir ; pratique en essai).
    'authentication_mode' => env('YOUSIGN_AUTH_MODE', 'no_otp'),

    // Envoi automatique à la signature dès le paiement confirmé.
    // En essai on garde true pour valider le flux de bout en bout.
    'auto_send' => (bool) env('CONVENTION_AUTO_SEND', true),

    // Emplacement du champ de signature sur le PDF (en points, origine haut-gauche).
    // Les deux parties sont placées côte à côte ; à affiner après revue juridique.
    'fields' => [
        'page'   => (int) env('YOUSIGN_FIELD_PAGE', 1),
        'width'  => 180,
        'height' => 60,
        'investor' => ['x' => 70,  'y' => 690],
        'owner'    => ['x' => 320, 'y' => 690],
    ],

    'timezone' => env('YOUSIGN_TIMEZONE', 'Africa/Dakar'),
    'timeout'  => (int) env('YOUSIGN_TIMEOUT', 30),
];
