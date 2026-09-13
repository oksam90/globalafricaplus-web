<?php

/*
|--------------------------------------------------------------------------
| DocuSeal — signature électronique auto-hébergée
|--------------------------------------------------------------------------
|
| Instance : https://sign.globalafricaplus.com (Docker, catalogue Hostinger)
| Docs     : https://www.docuseal.com/docs/api
|
| Authentification : en-tête `X-Auth-Token: <jeton>` (Paramètres → API).
|
| Flux retenu — POST /api/submissions/pdf :
|   on envoie directement le PDF de la convention (déjà produit par
|   ConventionGenerator via LibreOffice), sans créer de gabarit permanent.
|   Les emplacements de signature sont posés par COORDONNÉES, en fractions de
|   page (0 → 1), ce qui évite de devoir insérer des balises {{...}} dans les
|   modèles .docx validés juridiquement.
|
*/

return [
    /*
    | URL de base de l'instance. IMPORTANT : doit correspondre à l'« URL de
    | l'application » configurée dans DocuSeal (Paramètres → Compte), sinon les
    | liens de signature envoyés aux parties pointeront vers une autre adresse.
    */
    'base_url' => rtrim(env('DOCUSEAL_URL', 'https://sign.globalafricaplus.com'), '/'),

    'api_token' => env('DOCUSEAL_API_TOKEN'),

    'timeout' => (int) env('DOCUSEAL_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Mode d'envoi — dicté par l'édition de DocuSeal
    |--------------------------------------------------------------------------
    |
    | "oneoff"   → POST /submissions/pdf : on envoie le PDF déjà rempli par
    |              ConventionGenerator. Rien à maintenir dans DocuSeal.
    |              ⚠️ Réservé à l'ÉDITION PRO (l'édition libre répond 404
    |              « This feature is available in Pro Edition »).
    |
    | "template" → POST /submissions depuis un gabarit préparé une fois dans
    |              l'interface DocuSeal, les données étant transmises en
    |              `values`. Seul mode disponible en ÉDITION LIBRE.
    |
    | Vérifier ce que supporte l'instance : php artisan docuseal:check
    */
    'mode' => env('DOCUSEAL_MODE', 'template'),

    /*
    |--------------------------------------------------------------------------
    | Mode "template" — aiguillage type de convention → gabarit DocuSeal
    |--------------------------------------------------------------------------
    | Créer un gabarit par type dans l'interface, puis reporter son identifiant
    | ici. `php artisan docuseal:templates` liste les gabarits et le nom exact
    | de leurs champs.
    |
    | Les NOMS DE CHAMPS du gabarit doivent correspondre aux clés du contexte
    | de convention (cf. ConventionContext::build) pour être préremplis :
    |   investor_name, investor_address, investor_kyc_ref, company_name,
    |   company_legal_form, company_rccm, company_tax, company_address,
    |   company_representative, operator, amount_in_words, amount_figures,
    |   currency, payment_means, disposition_date, payout_account,
    |   contract_date, contract_place, jurisdiction_law
    | ainsi que jalon_1_desc / jalon_1_montant / jalon_1_date (idem 2 et 3).
    */
    'templates' => [
        'equity'   => env('DOCUSEAL_TEMPLATE_EQUITY'),
        'donation' => env('DOCUSEAL_TEMPLATE_DONATION'),
        'loan'     => env('DOCUSEAL_TEMPLATE_LOAN'),
        'reward'   => env('DOCUSEAL_TEMPLATE_REWARD'),
    ],

    /*
    | Envoi des emails de demande de signature par DocuSeal.
    | ⚠️ Nécessite un SMTP configuré dans DocuSeal (Paramètres → E-mail).
    | À false, aucun email n'est envoyé : on expose les liens de signature
    | (`embed_src`) directement dans l'application.
    */
    'send_email' => (bool) env('DOCUSEAL_SEND_EMAIL', false),

    /*
    | Ordre de signature :
    |   preserved → la 2e partie ne reçoit la demande qu'après signature de la 1re
    |   random    → les deux parties sont sollicitées simultanément
    */
    'order' => env('DOCUSEAL_ORDER', 'random'),

    /*
    | Retire les champs de formulaire du PDF signé (aplatissement).
    */
    'flatten' => (bool) env('DOCUSEAL_FLATTEN', true),

    /*
    | Redirection du signataire après finalisation (facultatif).
    */
    'completed_redirect_url' => env('DOCUSEAL_REDIRECT_URL')
        ?: rtrim(env('APP_URL', 'https://globalafricaplus.com'), '/') . '/dashboard',

    /*
    | Expiration de la demande de signature.
    */
    'expire_after_days' => (int) env('DOCUSEAL_EXPIRE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Emplacement des champs de signature
    |--------------------------------------------------------------------------
    | Coordonnées EXPRIMÉES EN FRACTION DE PAGE (0 = bord gauche/haut,
    | 1 = bord droit/bas), comme attendu par l'API DocuSeal.
    | `page` est indexée à partir de 1.
    |
    | Valeurs de départ : les deux blocs signature côte à côte en bas de la
    | première page. À réajuster après revue des modèles de convention.
    */
    'fields' => [
        'page'   => (int) env('DOCUSEAL_FIELD_PAGE', 1),
        'width'  => 0.28,
        'height' => 0.06,
        'investor' => ['x' => 0.10, 'y' => 0.82],
        'owner'    => ['x' => 0.58, 'y' => 0.82],
        // Champ « date de signature » sous chaque bloc.
        'date_height' => 0.025,
        'date_offset' => 0.07,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    | À saisir dans DocuSeal (Paramètres → Webhooks) :
    |   https://globalafricaplus.com/api/v1/webhooks/docuseal
    | Événements utiles : form.completed, form.declined, submission.completed,
    | submission.expired.
    |
    | DocuSeal signe chaque appel : en-tête
    |   X-Docuseal-Signature: <timestamp>.<hmac_sha256_hex>
    | où le HMAC porte sur « {timestamp}.{corps brut} », avec le secret COMPLET
    | (préfixe `whsec_` inclus) tel qu'affiché dans Webhooks → Sécurité → HMAC.
    | Tolérance d'horloge : 5 minutes.
    */
    'webhook_secret' => env('DOCUSEAL_WEBHOOK_SECRET'),
];
