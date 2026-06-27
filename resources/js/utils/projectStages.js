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

// ─────────────────────────────────────────────────────────────────────────────
// Section "Localisation de l'entreprise" — uniquement aux stades Lancement &
// Croissance. Un justificatif d'occupation des locaux (type au choix) + des
// pièces complémentaires (liens Drive). Les champs marqués `optional` ne sont
// pas requis pour le badge : "(selon pays)" pour le certificat fiscal, et les
// pièces propres à la location (quittances, attestation bailleur) qui ne
// s'appliquent pas à un titre de propriété.
// ─────────────────────────────────────────────────────────────────────────────
export const LOCALIZATION = {
    docTypes: [
        { value: 'bail_commercial', label: 'Contrat de bail commercial' },
        { value: 'attestation_domiciliation', label: 'Attestation de domiciliation' },
        { value: 'titre_propriete', label: 'Titre de propriété' },
    ],
    docs: [
        { key: 'loc_factures_services', label: "Factures de services (électricité, internet, eau) au nom de l'entreprise" },
        { key: 'loc_quittances_loyer', label: 'Quittances de loyer récentes', optional: true },
        { key: 'loc_attestation_bailleur', label: "Attestation du bailleur confirmant l'occupation", optional: true },
        { key: 'loc_certificat_domiciliation_fiscale', label: 'Certificat de domiciliation fiscale (selon pays)', optional: true },
        { key: 'loc_photos_bureau', label: 'Photos du bureau / local avec logo ou enseigne visible' },
        { key: 'loc_photo_equipe', label: 'Photo équipe en activité dans le local' },
    ],
};

// ─────────────────────────────────────────────────────────────────────────────
// Section "Preuves d'un financement antérieur" — TOUS les stades.
// Un select OUI/NON ; si OUI, on collecte les pièces justificatives (liens Drive).
// `optional: true` = non requis pour le badge (pièces propres aux prêts /
// décaissements en tranches / témoignages). Les 4 pièces cœur (contrat, lettre
// d'attribution, attestation institution, relevés bancaires) prouvent que le
// financement a bien été obtenu ET reçu → requises pour le badge.
// ─────────────────────────────────────────────────────────────────────────────
export const FINANCING = {
    fields: [
        { key: 'fin_contrat', label: 'Contrat de financement / convention signée' },
        { key: 'fin_lettre_attribution', label: "Lettre d'attribution ou d'acceptation du financement" },
        { key: 'fin_attestation_institution', label: "Attestation officielle de l'institution (programme, banque, microfinance)" },
        { key: 'fin_releves_bancaires', label: 'Relevés bancaires montrant la réception des fonds' },
        { key: 'fin_ordres_virement', label: 'Ordres de virement ou justificatifs de paiement', optional: true },
        { key: 'fin_tableau_decaissements', label: 'Tableau récapitulatif des décaissements (si en plusieurs tranches)', optional: true },
        { key: 'fin_rapports_utilisation', label: "Rapports d'utilisation ou rapports d'activité", optional: true },
        { key: 'fin_factures_depenses', label: 'Factures, devis ou justificatifs des dépenses réalisées', optional: true },
        { key: 'fin_presentation_investissements', label: 'Présentation des investissements effectués (équipement, développement produit, marketing…)', optional: true },
        { key: 'fin_indicateurs_cles', label: 'Indicateurs clés atteints (revenus, utilisateurs, croissance…)', optional: true },
        { key: 'fin_rapports_suivi', label: "Rapports de suivi du programme ou de l'institution financière", optional: true },
        { key: 'fin_temoignages', label: 'Témoignages ou lettres de recommandation (optionnel mais puissant)', optional: true },
        { key: 'fin_tableau_remboursement', label: 'Tableau de remboursement (si prêt)', optional: true },
        { key: 'fin_attestation_remboursement', label: 'Attestation de remboursement ou de situation à jour', optional: true },
        { key: 'fin_engagements_restants', label: 'État des engagements restants (encours)', optional: true },
    ],
};

// ─────────────────────────────────────────────────────────────────────────────
// Section "Preuves de l'apport personnel" — TOUS les stades.
// Select OUI/NON ; si OUI, on collecte les justificatifs d'apport (liens Drive).
// Cœur requis pour le badge : déclaration signée du montant, détail de la nature
// des apports, relevés bancaires des virements et attestation bancaire d'apport
// en capital. Le reste enrichit le dossier (facultatif pour le badge).
// ─────────────────────────────────────────────────────────────────────────────
export const EQUITY = {
    fields: [
        { key: 'eq_declaration_montant', label: "Déclaration signée du porteur de projet précisant le montant total de son apport" },
        { key: 'eq_detail_nature', label: 'Détail de la nature des apports (cash, matériel, dépenses)' },
        { key: 'eq_releves_bancaires', label: "Relevés bancaires montrant les virements vers le compte de l'entreprise" },
        { key: 'eq_attestation_bancaire', label: "Attestation bancaire confirmant l'apport en capital" },
        { key: 'eq_statuts_capital', label: 'Statuts de l\'entreprise mentionnant le capital social libéré', optional: true },
        { key: 'eq_cap_table', label: 'Répartition des parts (cap table)', optional: true },
        { key: 'eq_pv_constitution', label: "Procès-verbal de constitution ou d'augmentation de capital", optional: true },
        { key: 'eq_factures_achat', label: "Factures ou justificatifs d'achat (matériel, équipements…)", optional: true },
        { key: 'eq_bordereaux_depot', label: 'Bordereaux de dépôt (espèces ou chèques)', optional: true },
        { key: 'eq_rapports_evaluation', label: "Rapports d'évaluation des biens apportés", optional: true },
        { key: 'eq_liste_actifs', label: 'Liste détaillée des actifs intégrés au projet', optional: true },
        { key: 'eq_convention_compte_courant', label: 'Convention de compte courant signée', optional: true },
        { key: 'eq_preuves_virement', label: "Preuves de virement vers l'entreprise", optional: true },
        { key: 'eq_conditions_remboursement', label: 'Conditions de remboursement (si applicable)', optional: true },
        { key: 'eq_factures_depenses', label: 'Factures (développement produit, marketing, études…)', optional: true },
        { key: 'eq_contrats_prestataires', label: 'Contrats prestataires', optional: true },
        { key: 'eq_preuves_paiement', label: 'Preuves de paiement correspondantes', optional: true },
    ],
};

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
        localization: LOCALIZATION,
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
        localization: LOCALIZATION,
    },
};

/** Retourne la config du stade (fallback sur 'idea' si stade inconnu). */
export function stageConfig(stage) {
    return STAGE_CONFIG[stage] || STAGE_CONFIG.idea;
}

/**
 * Toutes les clés `stage_details` d'un stade (informations + documents +
 * localisation). Sert au pruning du payload côté formulaire pour ne garder
 * que les champs pertinents au stade choisi.
 */
export function stageDetailKeys(stage) {
    const cfg = stageConfig(stage);
    const keys = [
        ...cfg.info.map((f) => f.key),
        ...cfg.docs.map((d) => d.key),
    ];
    if (cfg.localization) {
        keys.push('loc_doc_type', 'loc_doc_link');
        keys.push(...cfg.localization.docs.map((d) => d.key));
    }
    // Section "Preuves d'un financement antérieur" — présente à tous les stades.
    keys.push('fin_has_prior');
    keys.push(...FINANCING.fields.map((f) => f.key));
    // Section "Preuves de l'apport personnel" — présente à tous les stades.
    keys.push('eq_has_prior');
    keys.push(...EQUITY.fields.map((f) => f.key));
    return keys;
}
