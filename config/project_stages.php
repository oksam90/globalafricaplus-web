<?php

/*
|--------------------------------------------------------------------------
| Champs requis par stade de projet (badges de confiance)
|--------------------------------------------------------------------------
|
| Liste, pour chaque stade, les clés `stage_details` qui doivent être
| renseignées pour décrocher les badges :
|   - "Informations à fournir — stade « … »"  → info_required
|   - "Documents requis — stade « … »"        → docs_required (+ pitch_deck_url)
|
| Les libellés affichés vivent côté front (resources/js/utils/projectStages.js).
| Ici on ne garde que les clés nécessaires au calcul de complétude.
|
| Les champs explicitement facultatifs dans la spec ("si disponibles",
| "si applicable") sont VOLONTAIREMENT exclus des listes _required afin de ne
| pas bloquer le badge — ils restent saisissables dans le formulaire :
|   - idea_wireframes          (« si disponibles »)
|   - scaling_esg              (« si applicable »)
|   - scaling_impact_report    (« si applicable »)
|
| Le pitch deck (champ projet `pitch_deck_url`) est toujours requis pour le
| badge "Documents requis" — vérifié séparément dans Project::trust_badges.
|
*/

// Clés requises pour le badge "Localisation de l'entreprise" (stades Lancement
// & Croissance). Le type de justificatif (loc_doc_type) + son lien (loc_doc_link)
// sont obligatoires. Champs volontairement exclus car facultatifs :
//   loc_quittances_loyer / loc_attestation_bailleur (propres à une location)
//   loc_certificat_domiciliation_fiscale (« selon pays »)
$localizationRequired = [
    'loc_doc_type',
    'loc_doc_link',
    'loc_factures_services',
    'loc_photos_bureau',
    'loc_photo_equipe',
];

// Clés requises pour le badge "Preuves d'un financement antérieur" (tous les
// stades, uniquement si fin_has_prior = "oui"). Cœur de preuve : le financement
// a bien été obtenu (contrat + lettre + attestation) ET reçu (relevés bancaires).
// Les autres pièces (usage, factures, KPIs, prêt, témoignages…) sont facultatives.
$financingRequired = [
    'fin_contrat',
    'fin_lettre_attribution',
    'fin_attestation_institution',
    'fin_releves_bancaires',
];

// Clés requises pour le badge "Preuves de l'apport personnel" (tous les stades,
// uniquement si eq_has_prior = "oui"). Cœur de preuve : déclaration du montant +
// nature des apports + relevés bancaires des virements + attestation bancaire.
// Les autres pièces (statuts, cap table, PV, factures, actifs…) sont facultatives.
$equityRequired = [
    'eq_declaration_montant',
    'eq_detail_nature',
    'eq_releves_bancaires',
    'eq_attestation_bancaire',
];

return [

    'idea' => [
        'info_required' => [
            'idea_problem',
            'idea_solution',
            'idea_market',
            'idea_competition',
            'idea_team',
            'idea_business_model',
            'idea_funds_usage',
        ],
        'docs_required' => [
            'idea_executive_summary',
            'idea_market_study',
            'idea_founders_bios',
            'idea_motivation_letter',
            // idea_wireframes : facultatif
        ],
    ],

    'mvp' => [
        'info_required' => [
            'mvp_demo',
            'mvp_user_feedback',
            'mvp_engagement_metrics',
            'mvp_value_proposition',
            'mvp_cac',
            'mvp_roadmap',
            'mvp_growth_assumptions',
            'mvp_funds_allocation',
        ],
        'docs_required' => [
            'mvp_user_testing_report',
            'mvp_business_plan',
            'mvp_financial_forecasts',
            'mvp_incorporation_docs',
            'mvp_cap_table',
            'mvp_ip',
        ],
    ],

    'launch' => [
        'info_required' => [
            'launch_revenue_mom',
            'launch_active_customers',
            'launch_cac_ltv',
            'launch_sales_channels',
            'launch_margins',
            'launch_pipeline',
            'launch_gtm',
            'launch_org_structure',
        ],
        'docs_required' => [
            'launch_business_plan',
            'launch_financial_statements',
            'launch_financial_forecasts',
            'launch_data_room',
            'launch_legal_dd',
            'launch_client_references',
            'launch_audit_report',
        ],
        'localization_required' => $localizationRequired,
    ],

    'scaling' => [
        'info_required' => [
            'scaling_arr_mrr',
            'scaling_market_share',
            'scaling_expansion',
            'scaling_profitability',
            'scaling_fundraising_history',
            'scaling_governance',
            'scaling_exit_strategy',
            // scaling_esg : facultatif
        ],
        'docs_required' => [
            'scaling_audited_financials',
            'scaling_dd_report',
            'scaling_shareholders_agreement',
            'scaling_data_room',
            'scaling_valuation',
            'scaling_information_memorandum',
            // scaling_impact_report : facultatif
        ],
        'localization_required' => $localizationRequired,
    ],

    // Sections transversales (tous stades) — clés sœurs, pas des stades.
    'financing_required' => $financingRequired,
    'equity_required'    => $equityRequired,

];
