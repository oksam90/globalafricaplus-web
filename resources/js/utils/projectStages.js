// ─────────────────────────────────────────────────────────────────────────────
// Configuration des informations & documents attendus selon le stade du projet.
//
//   info  : "Informations à fournir" → textareas (texte libre)
//   docs  : "Documents requis"        → liens (URL) vers les pièces hébergées
//   pitchLabel : libellé contextuel du pitch deck. Le pitch deck réutilise le
//                champ projet existant `pitch_deck_url` (non dupliqué).
//
// Partagé par le formulaire (CreateEdit.vue) et la fiche projet (Show.vue) pour
// garantir des libellés identiques côté saisie et côté lecture.
// ─────────────────────────────────────────────────────────────────────────────

export const STAGE_LABELS = { idea: 'Idée', mvp: 'MVP', launch: 'Lancement', scaling: 'Croissance' };

export function stageLabel(stage) {
    return STAGE_LABELS[stage] || '';
}

export const STAGE_CONFIG = {
    idea: {
        pitchLabel: 'Pitch deck (10-15 slides)',
        info: [
            { key: 'idea_problem', label: 'Description claire du problème identifié' },
            { key: 'idea_solution', label: 'Vision de la solution envisagée' },
            { key: 'idea_market', label: 'Analyse du marché cible et de sa taille' },
            { key: 'idea_competition', label: 'Identification de la concurrence existante' },
            { key: 'idea_team', label: "Profil et compétences de l'équipe fondatrice" },
            { key: 'idea_business_model', label: 'Modèle économique pressenti (comment générer des revenus)' },
            { key: 'idea_funds_usage', label: 'Usage prévu des fonds', placeholder: 'Le montant recherché est saisi plus haut. Détaillez ici son affectation.' },
        ],
        docs: [
            { key: 'idea_executive_summary', label: 'Executive summary (1-2 pages)' },
            { key: 'idea_market_study', label: 'Étude de marché préliminaire' },
            { key: 'idea_founders_bios', label: 'Biographies des fondateurs / CV' },
            { key: 'idea_motivation_letter', label: 'Lettre de motivation ou vision stratégique' },
            { key: 'idea_wireframes', label: 'Premiers wireframes ou maquettes (si disponibles)' },
        ],
    },
    mvp: {
        pitchLabel: 'Pitch deck actualisé avec démo (ou démo live)',
        info: [
            { key: 'mvp_demo', label: 'Démonstration du MVP fonctionnel' },
            { key: 'mvp_user_feedback', label: 'Retours des premiers utilisateurs / beta-testeurs' },
            { key: 'mvp_engagement_metrics', label: "Métriques d'engagement précoces (taux d'adoption, rétention)" },
            { key: 'mvp_value_proposition', label: 'Proposition de valeur validée empiriquement' },
            { key: 'mvp_cac', label: "Estimation précisée du coût d'acquisition client (CAC)" },
            { key: 'mvp_roadmap', label: 'Roadmap produit à 12-18 mois' },
            { key: 'mvp_growth_assumptions', label: 'Hypothèses de croissance et jalons' },
            { key: 'mvp_funds_allocation', label: 'Allocation détaillée du financement demandé', placeholder: 'Le montant recherché est saisi plus haut. Détaillez ici sa répartition.' },
        ],
        docs: [
            { key: 'mvp_user_testing_report', label: 'Rapport de tests utilisateurs' },
            { key: 'mvp_business_plan', label: "Plan d'affaires (business plan) version initiale" },
            { key: 'mvp_financial_forecasts', label: 'Prévisions financières sur 3 ans' },
            { key: 'mvp_incorporation_docs', label: 'Statuts de la société / documents de constitution' },
            { key: 'mvp_cap_table', label: 'Tableau de capitalisation (cap table)' },
            { key: 'mvp_ip', label: 'Propriété intellectuelle : dépôts, brevets, marques' },
        ],
    },
    launch: {
        pitchLabel: 'Pitch deck Série A avec données de traction',
        info: [
            { key: 'launch_revenue_mom', label: "Chiffre d'affaires actuel et croissance mensuelle (MoM)" },
            { key: 'launch_active_customers', label: 'Nombre de clients actifs et taux de rétention' },
            { key: 'launch_cac_ltv', label: "Coût d'acquisition client (CAC) et valeur vie client (LTV)" },
            { key: 'launch_sales_channels', label: 'Canaux de vente et de distribution utilisés' },
            { key: 'launch_margins', label: 'Marges brutes et seuil de rentabilité estimé' },
            { key: 'launch_pipeline', label: 'Pipeline commercial et contrats signés' },
            { key: 'launch_gtm', label: 'Stratégie go-to-market documentée' },
            { key: 'launch_org_structure', label: 'Structure organisationnelle et recrutements prévus' },
        ],
        docs: [
            { key: 'launch_business_plan', label: 'Business plan complet et détaillé' },
            { key: 'launch_financial_statements', label: 'États financiers réels (bilan, compte de résultat, flux de trésorerie)' },
            { key: 'launch_financial_forecasts', label: 'Prévisions financières sur 3-5 ans' },
            { key: 'launch_data_room', label: 'Data room complète (contrats clients, partenariats)' },
            { key: 'launch_legal_dd', label: "Due diligence légale (statuts, pacte d'actionnaires, IP)" },
            { key: 'launch_client_references', label: "Références clients (témoignages, cas d'usage)" },
            { key: 'launch_audit_report', label: "Rapport d'audit ou de commissariat aux comptes" },
        ],
    },
    scaling: {
        pitchLabel: 'Pitch deck Série B/C avec historique et projections',
        info: [
            { key: 'scaling_arr_mrr', label: 'Revenus récurrents annuels (ARR/MRR) et croissance YoY' },
            { key: 'scaling_market_share', label: 'Parts de marché conquises et positionnement concurrentiel' },
            { key: 'scaling_expansion', label: "Stratégie d'expansion géographique ou sectorielle" },
            { key: 'scaling_profitability', label: 'Indicateurs de rentabilité : EBITDA, marge nette' },
            { key: 'scaling_fundraising_history', label: 'Historique de levées de fonds et utilisation des capitaux' },
            { key: 'scaling_governance', label: "Gouvernance : conseil d'administration, comité de direction" },
            { key: 'scaling_exit_strategy', label: 'Plan de sortie pour les investisseurs (exit strategy)' },
            { key: 'scaling_esg', label: 'Indicateurs ESG / impact (si applicable)' },
        ],
        docs: [
            { key: 'scaling_audited_financials', label: 'États financiers audités (3 dernières années minimum)' },
            { key: 'scaling_dd_report', label: 'Rapport de due diligence financière et juridique complet' },
            { key: 'scaling_shareholders_agreement', label: "Pacte d'actionnaires actualisé" },
            { key: 'scaling_data_room', label: 'Data room exhaustive et organisée' },
            { key: 'scaling_valuation', label: "Valorisation indépendante de l'entreprise" },
            { key: 'scaling_impact_report', label: "Rapport d'impact ou RSE (si applicable)" },
            { key: 'scaling_information_memorandum', label: "Mémorandum d'information (Information Memorandum)" },
        ],
    },
};

/** Retourne la config du stade (fallback sur 'idea' si stade inconnu). */
export function stageConfig(stage) {
    return STAGE_CONFIG[stage] || STAGE_CONFIG.idea;
}
