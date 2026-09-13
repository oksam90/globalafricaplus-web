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
    | Envoi automatique à la signature dès le paiement confirmé.
    */
    'auto_send' => (bool) env('CONVENTION_AUTO_SEND', true),

    /*
    | Rôles des deux parties, tels qu'ils apparaissent dans le document et sur
    | l'écran de signature.
    */
    'roles' => [
        'investor' => env('SIGNATURE_ROLE_INVESTOR', 'Investisseur'),
        'owner'    => env('SIGNATURE_ROLE_OWNER', 'Porteur de projet'),
    ],
];
