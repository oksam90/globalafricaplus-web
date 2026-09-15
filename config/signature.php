<?php

/*
|--------------------------------------------------------------------------
| Signature électronique — sélection du prestataire
|--------------------------------------------------------------------------
|
| Depuis 2026-09, la signature des conventions passe par **DocuSeal**
| auto-hébergé (sign.globalafricaplus.com) : pas de coût par signature, les
| documents restent sur notre infrastructure.
|
| La configuration Yousign (config/yousign.php, YousignClient) est CONSERVÉE :
| SIGNATURE_PROVIDER=yousign suffit à y revenir. Les conventions déjà envoyées
| via Yousign continuent d'être suivies chez Yousign, quel que soit le
| prestataire actif (le fournisseur est mémorisé sur chaque investissement).
|
*/

return [
    /*
    | Prestataire actif : "docuseal" | "yousign"
    */
    'provider' => env('SIGNATURE_PROVIDER', 'docuseal'),

    /*
    | Envoi automatique à la signature (false = mise à la signature manuelle).
    */
    'auto_send' => (bool) env('CONVENTION_AUTO_SEND', true),

    /*
    |--------------------------------------------------------------------------
    | Quand PRODUIRE la convention
    |--------------------------------------------------------------------------
    |
    | Produire un document n'a aucun effet vers l'extérieur : personne n'est
    | sollicité, aucun email ne part. On le fait donc au plus tôt, pour que
    | l'investisseur ait son contrat sous les yeux au moment où il s'engage.
    |
    |   "validation"   — au clic sur « Payer » (défaut)
    |   "payment"      — seulement à l'encaissement
    |
    */
    'generate_on' => env('CONVENTION_GENERATE_ON', 'validation'),

    /*
    |--------------------------------------------------------------------------
    | Quand ENVOYER à la signature
    |--------------------------------------------------------------------------
    |
    | Envoyer s'adresse à un TIERS : le porteur de projet reçoit une demande de
    | signature, et un email si DOCUSEAL_SEND_EMAIL est actif. Cela ne doit pas
    | se produire pour de l'argent qui n'est jamais arrivé.
    |
    |   "first_payment" — au premier encaissement confirmé (défaut). En
    |                     paiement comptant, quelques secondes après la
    |                     validation ; en fractionné, dès la 1re échéance —
    |                     la convention existe donc le jour même, et non au
    |                     douzième mois.
    |
    |   "validation"    — dès le clic sur « Payer », avant tout encaissement.
    |                     Un abandon au checkout — fréquent en mobile money :
    |                     solde insuffisant, PIN raté, session expirée — laisse
    |                     alors une demande de signature orpheline chez le
    |                     porteur, et chaque nouvelle tentative en crée une de
    |                     plus.
    |
    |   "full_payment"  — à l'encaissement intégral (comportement historique).
    |
    */
    'send_on' => env('CONVENTION_SEND_ON', 'first_payment'),

    /*
    | Rôles des deux parties, tels qu'ils apparaissent dans le document et sur
    | l'écran de signature.
    */
    'roles' => [
        'investor' => env('SIGNATURE_ROLE_INVESTOR', 'Investisseur'),
        'owner'    => env('SIGNATURE_ROLE_OWNER', 'Porteur de projet'),
    ],
];
