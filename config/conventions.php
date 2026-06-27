<?php

/*
|--------------------------------------------------------------------------
| Conventions d'investissement — mapping technique
|--------------------------------------------------------------------------
|
| Objectif : quand un investissement est confirmé, fabriquer automatiquement
| le BON contrat, déjà rempli avec les vraies données — sans choix manuel ni
| copier-coller.
|
| Trois ingrédients :
|   1) le champ `investments.type` (equity|donation|loan|reward) — l'étiquette ;
|   2) `templates`  — l'AIGUILLAGE : type → gabarit .docx ;
|   3) `injection`  — l'INJECTION : pour chaque placeholder du gabarit, la
|      séquence ordonnée des sources à insérer, occurrence par occurrence.
|
| Les gabarits sont des modèles « à trous » ([À COMPLÉTER] en doré). Chaque
| trou est un run de texte atomique dans le .docx ; on remplace donc la k-ième
| occurrence d'un placeholder par la k-ième source listée ci-dessous.
|
| Valeurs spéciales d'une séquence d'injection :
|   - 'KEEP'                → on laisse le placeholder en l'état (champ qui
|                             relève d'un conseil juridique : taux, durée,
|                             nombre de parts, signatures…) ;
|   - 'milestones.{i}.{f}'  → ligne i (0..2) du tableau des jalons, champ
|                             f ∈ {desc, amount, date, proofs} ;
|   - toute autre chaîne     → clé du contexte (cf. ConventionContext).
|
| Quand la séquence d'un placeholder est épuisée, les occurrences restantes
| sont laissées intactes (ex. dates de signature, mentions d'annexe).
|
*/

return [

    // Tiers payeur agréé (encaissement / séquestre / décaissement).
    'operator' => env('CONVENTION_OPERATOR', 'PayDunya'),

    // Droit applicable injecté dans « régie par le droit [SÉNÉGALAIS / OHADA] ».
    'jurisdiction_law' => 'OHADA',

    // Lieu de signature par défaut si la ville du projet est absente.
    'default_place' => 'Dakar',

    // Disque de stockage des documents générés + dossier.
    'disk' => 'local',
    'storage_dir' => 'contracts',

    // Dossier des gabarits source.
    'templates_dir' => base_path('docs/Convention'),

    // Étape 5 — conversion .docx → PDF via LibreOffice (headless).
    'pdf' => [
        'enabled' => (bool) env('CONVENTION_PDF', true),
        // Binaire LibreOffice : "soffice" (Linux/VPS) ou chemin complet sous Windows.
        'binary'  => env('LIBREOFFICE_BIN', 'soffice'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 1) + 2) AIGUILLAGE — type → gabarit
    |--------------------------------------------------------------------------
    | `party` / `counterparty` = rôles tels que nommés dans le contrat
    | (utiles pour le journal et la signature).
    */
    'templates' => [
        'equity' => [
            'file'         => 'Convention_Participation_equity_GlobalAfricaPlus.docx',
            'label'        => 'Participation (equity)',
            'party'        => 'Investisseur',
            'counterparty' => 'Porteur',
            'payout_placeholder' => '[COMPTE DU LE PORTEUR]',
        ],
        'donation' => [
            'file'         => 'Convention_Don_GlobalAfricaPlus.docx',
            'label'        => 'Don',
            'party'        => 'Donateur',
            'counterparty' => 'Bénéficiaire',
            'payout_placeholder' => '[COMPTE DU LE BÉNÉFICIAIRE]',
        ],
        'loan' => [
            'file'         => 'Convention_Pret_GlobalAfricaPlus.docx',
            'label'        => 'Prêt',
            'party'        => 'Prêteur',
            'counterparty' => 'Emprunteur',
            'payout_placeholder' => "[COMPTE DU L'EMPRUNTEUR]",
        ],
        'reward' => [
            'file'         => 'Convention_Contrepartie_reward_GlobalAfricaPlus.docx',
            'label'        => 'Contrepartie (reward)',
            'party'        => 'Contributeur',
            'counterparty' => 'Porteur',
            'payout_placeholder' => '[COMPTE DU LE PORTEUR]',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 3) INJECTION — placeholder → séquence ordonnée de sources
    |--------------------------------------------------------------------------
    | Plan COMMUN à tous les gabarits (la trame parties / montant / séquestre /
    | jalons est identique d'un type à l'autre). Le placeholder de compte de
    | décaissement diffère selon le type : il est injecté dynamiquement par le
    | générateur à partir de `templates.*.payout_placeholder`.
    */
    'injection' => [

        // ── Parties ───────────────────────────────────────────────────────
        '[NOM / RAISON SOCIALE]'              => ['investor_name'],
        // Pas de date de naissance en base → laissé au conseil.
        '[NÉ(E) LE / IMMATRICULÉ(E) SOUS]'    => ['KEEP'],
        '[ADRESSE, PAYS]'                     => ['investor_address', 'company_address'],
        '[ID]'                                => ['investor_kyc_ref'],
        '[RAISON SOCIALE]'                    => ['company_name'],
        '[FORME]'                             => ['company_legal_form'],
        '[N°]'                                => ['company_rccm'],
        '[NINEA / N°]'                        => ['company_tax'],
        '[NOM, QUALITÉ]'                      => ['company_representative'],

        // ── Opérateur de paiement ─────────────────────────────────────────
        '[OPÉRATEUR DE PAIEMENT AGRÉÉ — ex. PayDunya]' => ['operator'],

        // ── Montant et devise ─────────────────────────────────────────────
        '[MONTANT EN CHIFFRES ET LETTRES]'    => ['amount_in_words'],
        '[DEVISE]'                            => ['currency'],
        '[MOYEN]'                             => ['payment_means'],

        // ── Tableau des jalons (J1, J2, J3) + total d'annexe ──────────────
        // 1re occurrence de [À COMPLÉTER] = exemple de l'avertissement → KEEP.
        '[À COMPLÉTER]' => ['KEEP', 'milestones.0.desc', 'milestones.1.desc', 'milestones.2.desc'],
        '[MONTANT]'     => ['milestones.0.amount', 'milestones.1.amount', 'milestones.2.amount', 'amount_figures'],
        // [DATE] : mise à disposition, puis 3 échéances de jalons, puis date du
        // contrat. Les dates de signature qui suivent restent à compléter.
        '[DATE]'        => ['disposition_date', 'milestones.0.date', 'milestones.1.date', 'milestones.2.date', 'contract_date'],
        '[…]'           => ['milestones.0.proofs', 'milestones.1.proofs', 'milestones.2.proofs'],

        // ── Clôture ───────────────────────────────────────────────────────
        '[LIEU]'                => ['contract_place'],
        '[SÉNÉGALAIS / OHADA]'  => ['jurisdiction_law'],
        // Juridiction/arbitrage = choix juridique → laissé au conseil.
        '[JURIDICTION / ARBITRAGE]' => ['KEEP'],
    ],
];
