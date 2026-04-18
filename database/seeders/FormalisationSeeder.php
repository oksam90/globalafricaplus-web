<?php

namespace Database\Seeders;

use App\Models\BusinessPlanTemplate;
use App\Models\FormalizationProgress;
use App\Models\FormalizationStep;
use App\Models\MicrofinancePartner;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FormalisationSeeder extends Seeder
{
    public function run(): void
    {
        // ── SÉNÉGAL — Parcours de formalisation (RG-029) ──
        $senegalSteps = [
            [
                'title' => 'Choisir la forme juridique',
                'description' => "Déterminez le statut juridique adapté : entreprise individuelle, SUARL, SARL, SA, GIE. Le choix dépend du nombre d'associés, du capital social et de l'activité prévue.",
                'institution' => 'APIX (Agence de Promotion des Investissements)',
                'required_documents' => ['Pièce d\'identité', 'Justificatif de domicile'],
                'estimated_duration' => '1-2 jours',
                'estimated_cost' => 'Gratuit (consultation)',
                'link' => 'https://investinsenegal.com',
                'tips' => "Pour un auto-entrepreneur, la SUARL est idéale : capital minimum de 1 FCFA, gestion simplifiée. Pour plusieurs associés, la SARL requiert un minimum de 100 000 FCFA.",
            ],
            [
                'title' => 'Rédiger les statuts de l\'entreprise',
                'description' => "Établissez les statuts en précisant : dénomination sociale, objet, siège, capital, répartition des parts, gérance. Faites-les certifier par un notaire si SA.",
                'institution' => 'Notaire / Centre de Gestion Agréé',
                'required_documents' => ['Modèle de statuts', 'Pièces d\'identité des associés', 'Attestation de domiciliation'],
                'estimated_duration' => '2-5 jours',
                'estimated_cost' => '50 000 - 200 000 XOF',
                'tips' => "Des modèles gratuits sont disponibles sur la plateforme. Vous pouvez aussi utiliser les templates de business plan Africa+ pour structurer vos statuts.",
            ],
            [
                'title' => 'Immatriculation au RCCM',
                'description' => "Inscrivez votre entreprise au Registre du Commerce et du Crédit Mobilier via le guichet unique de l'APIX. C'est l'acte fondateur de la personnalité morale.",
                'institution' => 'APIX – Guichet Unique',
                'required_documents' => ['Statuts signés', 'PV de nomination du gérant', 'Casier judiciaire', 'Attestation de domiciliation', 'Pièce d\'identité du gérant'],
                'estimated_duration' => '2-3 jours ouvrés',
                'estimated_cost' => '15 000 - 50 000 XOF',
                'link' => 'https://creationentreprise.sn',
                'tips' => "Le processus est dématérialisé via creationentreprise.sn. Délai officiel : 48h. Gardez votre numéro RCCM, il est exigé pour toutes les démarches fiscales.",
            ],
            [
                'title' => 'Obtenir le NINEA',
                'description' => "Le Numéro d'Identification Nationale des Entreprises et Associations est délivré automatiquement lors de l'immatriculation au RCCM. Il sert d'identifiant fiscal unique.",
                'institution' => 'Direction Générale des Impôts et Domaines (DGID)',
                'required_documents' => ['Numéro RCCM', 'Statuts'],
                'estimated_duration' => 'Automatique (inclus dans le RCCM)',
                'estimated_cost' => 'Gratuit',
                'tips' => "Le NINEA est attribué simultanément avec le RCCM via le guichet unique. Vérifiez que le numéro figure bien sur votre attestation d'immatriculation.",
            ],
            [
                'title' => 'Déclaration fiscale d\'existence',
                'description' => "Déclarez votre entreprise auprès de la DGID pour le régime fiscal applicable. Choisissez entre régime réel (CA > 50M XOF), simplifié (CA > 30M) ou micro-entreprise.",
                'institution' => 'Direction Générale des Impôts et Domaines (DGID)',
                'required_documents' => ['Attestation RCCM', 'NINEA', 'Statuts', 'Contrat de bail'],
                'estimated_duration' => '1-2 jours',
                'estimated_cost' => 'Gratuit',
                'link' => 'https://dgid.sn',
                'tips' => "Le régime micro-entreprise est le plus simple pour démarrer : CGU (Contribution Globale Unique) entre 0,5% et 3% du CA.",
            ],
            [
                'title' => 'Obtenir la patente',
                'description' => "La patente est un impôt local obligatoire pour toute activité commerciale. Elle est calculée en fonction du chiffre d'affaires prévisionnel et de la localisation.",
                'institution' => 'Mairie / Collectivité locale',
                'required_documents' => ['Attestation RCCM', 'Déclaration fiscale', 'Contrat de bail'],
                'estimated_duration' => '3-7 jours',
                'estimated_cost' => 'Variable selon CA (minimum 5 000 XOF)',
                'tips' => "La patente est due au 1er janvier de chaque année. Les nouvelles entreprises bénéficient souvent d'exonérations temporaires dans les ZES.",
            ],
            [
                'title' => 'Ouvrir un compte bancaire professionnel',
                'description' => "Ouvrez un compte dédié à l'entreprise dans une banque commerciale. Déposez le capital social si requis par la forme juridique (SARL, SA).",
                'institution' => 'Banque commerciale (CBAO, Société Générale, BOA, etc.)',
                'required_documents' => ['Statuts', 'RCCM', 'NINEA', 'Pièce d\'identité du gérant', 'Procès-verbal de nomination'],
                'estimated_duration' => '1-3 jours',
                'estimated_cost' => 'Variable selon la banque',
                'tips' => "Comparez les offres : frais de tenue de compte, mobile banking, facilités de caisse. Les banques en ligne et néobanques (Wave, Orange Bank) proposent souvent des offres avantageuses pour les PME.",
            ],
        ];

        foreach ($senegalSteps as $i => $step) {
            FormalizationStep::updateOrCreate(
                ['country' => 'Sénégal', 'order' => $i + 1],
                array_merge($step, [
                    'slug' => Str::slug($step['title']),
                ])
            );
        }

        // ── CÔTE D'IVOIRE ──
        $ciSteps = [
            [
                'title' => 'Choisir la forme juridique',
                'description' => "Sélectionnez entre entreprise individuelle, SARL, SAS, SA ou GIE selon vos besoins. Le CEPICI offre un accompagnement gratuit pour le choix.",
                'institution' => 'CEPICI (Centre de Promotion des Investissements)',
                'required_documents' => ['CNI ou passeport'],
                'estimated_duration' => '1 jour',
                'estimated_cost' => 'Gratuit',
                'link' => 'https://cepici.gouv.ci',
                'tips' => "La SAS est de plus en plus privilégiée pour les startups : flexibilité statutaire, pas de capital minimum, décisions simplifiées.",
            ],
            [
                'title' => 'Enregistrement au guichet unique du CEPICI',
                'description' => "Le CEPICI centralise toutes les formalités : immatriculation au RCCM, obtention du NCC (Numéro de Compte Contribuable), inscription à la CNPS, et publication au Journal Officiel.",
                'institution' => 'CEPICI – Guichet Unique',
                'required_documents' => ['Statuts notariés', 'Déclaration de souscription et versement', 'Casier judiciaire', 'Certificat de résidence', 'Photos d\'identité'],
                'estimated_duration' => '24-72 heures',
                'estimated_cost' => '15 000 - 80 000 XOF',
                'link' => 'https://cepici.gouv.ci/creer-son-entreprise',
                'tips' => "Depuis 2021, la création est possible 100% en ligne via la plateforme eRegulations. Délai record : 24h pour une SARL.",
            ],
            [
                'title' => 'Obtenir le NCC (Numéro de Compte Contribuable)',
                'description' => "Identifiant fiscal unique délivré par la DGI. Nécessaire pour toute déclaration fiscale et facturation formelle.",
                'institution' => 'Direction Générale des Impôts (DGI)',
                'required_documents' => ['RCCM', 'Statuts', 'Pièce d\'identité du gérant'],
                'estimated_duration' => 'Inclus au CEPICI',
                'estimated_cost' => 'Gratuit',
                'tips' => "Conservez bien votre NCC : il figure sur toutes les factures, déclarations TVA et communications avec l'administration fiscale.",
            ],
            [
                'title' => 'Affiliation à la CNPS',
                'description' => "Immatriculation obligatoire à la Caisse Nationale de Prévoyance Sociale pour couvrir vos employés (allocations familiales, accidents du travail, retraite).",
                'institution' => 'CNPS',
                'required_documents' => ['RCCM', 'NCC', 'Liste des employés'],
                'estimated_duration' => '2-5 jours',
                'estimated_cost' => 'Gratuit (cotisations mensuelles ensuite)',
                'tips' => "L'affiliation est obligatoire dès le premier salarié. Le taux patronal est d'environ 15,75% du salaire brut.",
            ],
            [
                'title' => 'Obtenir la patente et ouvrir un compte bancaire',
                'description' => "Demandez la patente auprès de la mairie et ouvrez un compte professionnel dans une banque commerciale ivoirienne.",
                'institution' => 'Mairie + Banque commerciale',
                'required_documents' => ['RCCM', 'NCC', 'Statuts', 'Attestation de domiciliation'],
                'estimated_duration' => '3-5 jours',
                'estimated_cost' => 'Patente variable + frais bancaires',
                'tips' => "Les zones franches industrielles offrent des exonérations de patente pendant 5 ans. Renseignez-vous via le portail ZES de la plateforme.",
            ],
        ];

        foreach ($ciSteps as $i => $step) {
            FormalizationStep::updateOrCreate(
                ['country' => "Côte d'Ivoire", 'order' => $i + 1],
                array_merge($step, ['slug' => Str::slug($step['title']) . '-ci'])
            );
        }

        // ── MALI ──
        $maliSteps = [
            [
                'title' => 'Choisir la forme juridique',
                'description' => "Optez pour l'entreprise individuelle, SARL, SA ou GIE. L'API-Mali accompagne gratuitement les entrepreneurs dans ce choix.",
                'institution' => 'API-Mali (Agence pour la Promotion des Investissements)',
                'required_documents' => ['Pièce d\'identité'],
                'estimated_duration' => '1 jour',
                'estimated_cost' => 'Gratuit',
                'link' => 'https://apimali.gov.ml',
                'tips' => "La SARL reste la forme la plus populaire au Mali avec un capital minimum de 100 000 FCFA.",
            ],
            [
                'title' => 'Immatriculation au guichet unique de l\'API-Mali',
                'description' => "Le guichet unique centralise : immatriculation RCCM, NIF (Numéro d'Identification Fiscale), inscription à l'INPS (sécurité sociale). Procédure en 72h.",
                'institution' => 'API-Mali – Guichet Unique',
                'required_documents' => ['Statuts signés', 'PV de nomination', 'Casier judiciaire', 'Extrait d\'acte de naissance', 'Certificat de résidence'],
                'estimated_duration' => '72 heures',
                'estimated_cost' => '18 000 - 60 000 XOF',
                'tips' => "L'API-Mali délivre les documents en une seule visite. Prenez rendez-vous en ligne pour éviter l'attente.",
            ],
            [
                'title' => 'Obtenir le NIF',
                'description' => "Le Numéro d'Identification Fiscale est attribué par la Direction Générale des Impôts. Il est indispensable pour toute activité commerciale formelle.",
                'institution' => 'DGI Mali',
                'required_documents' => ['RCCM', 'Statuts'],
                'estimated_duration' => 'Inclus au guichet unique',
                'estimated_cost' => 'Gratuit',
                'tips' => "Le NIF est attribué automatiquement via le guichet unique.",
            ],
            [
                'title' => 'Obtenir la patente et ouvrir un compte bancaire',
                'description' => "Déclarez votre activité à la mairie pour la patente et ouvrez un compte professionnel dans une banque (BDM, BMS, Ecobank, etc.).",
                'institution' => 'Mairie + Banque commerciale',
                'required_documents' => ['RCCM', 'NIF', 'Statuts', 'Contrat de bail'],
                'estimated_duration' => '3-7 jours',
                'estimated_cost' => 'Variable',
                'tips' => "Les zones économiques de Bamako offrent des tarifs préférentiels de patente pour les nouvelles entreprises.",
            ],
        ];

        foreach ($maliSteps as $i => $step) {
            FormalizationStep::updateOrCreate(
                ['country' => 'Mali', 'order' => $i + 1],
                array_merge($step, ['slug' => Str::slug($step['title']) . '-mali'])
            );
        }

        // ── BUSINESS PLAN TEMPLATES (RG-030: gratuit) ──
        $templates = [
            [
                'title' => 'Business Plan — Agriculture & Agritech',
                'sector' => 'agriculture',
                'description' => "Modèle adapté aux projets agricoles africains : cultures vivrières, transformation agroalimentaire, agritech, élevage. Inclut les spécificités climatiques et les canaux de distribution locaux.",
                'sections' => [
                    ['title' => 'Résumé exécutif', 'prompt' => 'Décrivez votre projet agricole en 300 mots max : problème, solution, marché cible, avantage concurrentiel.'],
                    ['title' => 'Analyse de marché', 'prompt' => 'Taille du marché, tendances, concurrence locale, demande (export, marché domestique, transformation).'],
                    ['title' => 'Produits / Services', 'prompt' => 'Détaillez vos cultures, produits transformés ou services agritech. Calendrier de production, rendements attendus.'],
                    ['title' => 'Stratégie commerciale', 'prompt' => 'Canaux de vente (marchés locaux, coopératives, export), prix, promotion, partenariats avec les distributeurs.'],
                    ['title' => 'Plan opérationnel', 'prompt' => 'Localisation, besoins en terre/eau/intrants, équipement, logistique, certification qualité (bio, commerce équitable).'],
                    ['title' => 'Équipe', 'prompt' => 'Présentez l\'équipe fondatrice, les compétences agricoles et managériales, les besoins en recrutement.'],
                    ['title' => 'Plan financier', 'prompt' => 'Investissement initial, coûts d\'exploitation, compte de résultat prévisionnel (3 ans), trésorerie, point mort.'],
                    ['title' => 'Impact social & environnemental', 'prompt' => 'Emplois créés, revenus des agriculteurs, sécurité alimentaire, durabilité environnementale, ODD ciblés.'],
                ],
            ],
            [
                'title' => 'Business Plan — Commerce & Retail',
                'sector' => 'commerce',
                'description' => "Modèle pour les activités commerciales : boutique, e-commerce, import-export, distribution. Adapté au marché africain avec intégration mobile money.",
                'sections' => [
                    ['title' => 'Résumé exécutif', 'prompt' => 'Décrivez votre activité commerciale : produits, marché, modèle économique, proposition de valeur unique.'],
                    ['title' => 'Analyse de marché', 'prompt' => 'Étude du marché local, pouvoir d\'achat, habitudes de consommation, concurrence directe et indirecte.'],
                    ['title' => 'Catalogue produits', 'prompt' => 'Gamme de produits, fournisseurs, marge brute, politique de prix, gestion des stocks.'],
                    ['title' => 'Stratégie de vente', 'prompt' => 'Point de vente physique, e-commerce, réseaux sociaux, mobile money, livraison, fidélisation.'],
                    ['title' => 'Plan opérationnel', 'prompt' => 'Local commercial, aménagement, personnel, horaires, logistique approvisionnement.'],
                    ['title' => 'Plan financier', 'prompt' => 'Investissement, stock initial, charges fixes/variables, prévisionnel 3 ans, besoin en fonds de roulement.'],
                ],
            ],
            [
                'title' => 'Business Plan — Artisanat & Production',
                'sector' => 'artisanat',
                'description' => "Modèle pour les artisans et micro-producteurs : textile, maroquinerie, bois, métallurgie, bijouterie. Valorisation du savoir-faire africain.",
                'sections' => [
                    ['title' => 'Résumé exécutif', 'prompt' => 'Décrivez votre activité artisanale : savoir-faire, produits, marché, valeur ajoutée culturelle et artistique.'],
                    ['title' => 'Produits & savoir-faire', 'prompt' => 'Détaillez vos créations, techniques, matériaux, temps de production, capacité, qualité.'],
                    ['title' => 'Marché & positionnement', 'prompt' => 'Marché local, tourisme, export, e-commerce international, positionnement prix (luxe, milieu de gamme).'],
                    ['title' => 'Production & approvisionnement', 'prompt' => 'Atelier, outils, matières premières, apprentis, sous-traitance, contrôle qualité.'],
                    ['title' => 'Commercialisation', 'prompt' => 'Marchés, boutiques, plateformes en ligne (Etsy, Amazon Handmade), salons, galeries, réseaux sociaux.'],
                    ['title' => 'Plan financier', 'prompt' => 'Investissement atelier, coût de revient par pièce, marge, prévisionnel, besoin de financement.'],
                ],
            ],
            [
                'title' => 'Business Plan — Services & Consulting',
                'sector' => 'services',
                'description' => "Modèle pour les prestataires de services : consulting, formation, digital, comptabilité, santé. Adapté aux réalités du marché des services en Afrique.",
                'sections' => [
                    ['title' => 'Résumé exécutif', 'prompt' => 'Décrivez votre offre de services, votre expertise, votre marché cible et votre avantage compétitif.'],
                    ['title' => 'Offre de services', 'prompt' => 'Détaillez vos prestations, tarifs, conditions, livrables, différenciation par rapport à la concurrence.'],
                    ['title' => 'Marché & clientèle', 'prompt' => 'Segmentation client, taille du marché, tendances, processus d\'achat, cycle de vente.'],
                    ['title' => 'Marketing & développement commercial', 'prompt' => 'Stratégie d\'acquisition : networking, digital, recommandations, appels d\'offres, partenariats.'],
                    ['title' => 'Organisation', 'prompt' => 'Équipe, compétences, formation continue, outils numériques, sous-traitance.'],
                    ['title' => 'Plan financier', 'prompt' => 'Honoraires, charges, prévisionnel, rentabilité, investissements en formation et marketing.'],
                ],
            ],
            [
                'title' => 'Business Plan — Tech & Innovation',
                'sector' => 'tech',
                'description' => "Modèle pour les startups tech africaines : fintech, healthtech, edtech, SaaS, marketplace. Inclut les métriques spécifiques aux startups.",
                'sections' => [
                    ['title' => 'Résumé exécutif', 'prompt' => 'Problème résolu, solution technologique, taille du marché, traction actuelle, montant recherché.'],
                    ['title' => 'Problème & solution', 'prompt' => 'Quel problème résolvez-vous ? Pour qui ? Quelle est votre solution ? En quoi est-elle innovante ?'],
                    ['title' => 'Produit & technologie', 'prompt' => 'Stack technique, fonctionnalités clés, roadmap produit, avantage technologique, IP.'],
                    ['title' => 'Modèle économique', 'prompt' => 'Business model (SaaS, marketplace, freemium, transaction), pricing, unit economics, LTV/CAC.'],
                    ['title' => 'Traction & métriques', 'prompt' => 'Utilisateurs, MRR/ARR, croissance, rétention, NPS, partenariats, clients référents.'],
                    ['title' => 'Marché & concurrence', 'prompt' => 'TAM/SAM/SOM, analyse concurrentielle, barrières à l\'entrée, moat, go-to-market.'],
                    ['title' => 'Équipe', 'prompt' => 'Fondateurs, CTO, advisors, recrutements prévus, culture d\'entreprise.'],
                    ['title' => 'Plan financier', 'prompt' => 'Burn rate, runway, prévisionnel 3-5 ans, use of funds, cap table, levée de fonds.'],
                ],
            ],
        ];

        foreach ($templates as $tpl) {
            BusinessPlanTemplate::updateOrCreate(
                ['slug' => Str::slug($tpl['title'])],
                array_merge($tpl, [
                    'slug' => Str::slug($tpl['title']),
                    'downloads_count' => rand(15, 320),
                ])
            );
        }

        // ── MICROFINANCE PARTNERS ──
        $partners = [
            [
                'name' => 'PAMECAS',
                'country' => 'Sénégal',
                'description' => "Premier réseau de microfinance au Sénégal. Offre des produits d'épargne, de crédit et de transfert d'argent adaptés aux micro-entrepreneurs.",
                'type' => 'IMF',
                'products' => ['Micro-crédit', 'Épargne', 'Crédit PME', 'Mobile money'],
                'min_amount' => '50 000 XOF',
                'max_amount' => '10 000 000 XOF',
                'interest_rate' => '1,5% - 2% mensuel',
                'website' => 'https://pamecas.sn',
            ],
            [
                'name' => 'CMS (Crédit Mutuel du Sénégal)',
                'country' => 'Sénégal',
                'description' => "Réseau mutualiste offrant des services de proximité : épargne, crédit, assurance. Forte présence en zone rurale.",
                'type' => 'IMF',
                'products' => ['Crédit rural', 'Micro-crédit', 'Épargne tontine', 'Crédit femme'],
                'min_amount' => '25 000 XOF',
                'max_amount' => '5 000 000 XOF',
                'interest_rate' => '1% - 1,8% mensuel',
                'website' => 'https://cms.sn',
            ],
            [
                'name' => 'Advans Côte d\'Ivoire',
                'country' => "Côte d'Ivoire",
                'description' => "Institution de microfinance spécialisée dans le financement des TPE et PME. Offre des crédits rapides et adaptés.",
                'type' => 'IMF',
                'products' => ['Crédit fonds de roulement', 'Crédit investissement', 'Crédit agricole', 'Épargne'],
                'min_amount' => '100 000 XOF',
                'max_amount' => '50 000 000 XOF',
                'interest_rate' => '1,2% - 2% mensuel',
                'website' => 'https://advansgroup.com/ci',
            ],
            [
                'name' => 'COOPEC-Mali',
                'country' => 'Mali',
                'description' => "Coopérative d'épargne et de crédit pour les entrepreneurs maliens. Offre des conditions préférentielles pour les femmes entrepreneures.",
                'type' => 'Coopérative',
                'products' => ['Micro-crédit', 'Crédit solidaire', 'Épargne', 'Crédit femme entrepreneur'],
                'min_amount' => '50 000 XOF',
                'max_amount' => '5 000 000 XOF',
                'interest_rate' => '1% - 1,5% mensuel',
            ],
            [
                'name' => 'Wave',
                'country' => 'Sénégal',
                'description' => "Fintech mobile money offrant des services financiers à faible coût. Transferts gratuits, paiements marchands, micro-épargne.",
                'type' => 'Fintech',
                'products' => ['Transfert d\'argent', 'Paiement marchand', 'Micro-épargne'],
                'min_amount' => '500 XOF',
                'max_amount' => '2 000 000 XOF',
                'interest_rate' => 'N/A (pas de crédit)',
                'website' => 'https://wave.com',
            ],
        ];

        foreach ($partners as $p) {
            MicrofinancePartner::updateOrCreate(
                ['slug' => Str::slug($p['name'])],
                array_merge($p, ['slug' => Str::slug($p['name'])])
            );
        }

        // ── DEMO PROGRESS for existing entrepreneur (aminata@africaplus.test) ──
        $aminata = User::where('email', 'aminata@africaplus.test')->first();
        if ($aminata) {
            $senegalStepIds = FormalizationStep::where('country', 'Sénégal')
                ->orderBy('order')
                ->pluck('id');

            // Aminata has completed steps 1-4, step 5 in progress
            foreach ($senegalStepIds as $i => $stepId) {
                if ($i < 4) {
                    FormalizationProgress::updateOrCreate(
                        ['user_id' => $aminata->id, 'step_id' => $stepId],
                        ['status' => 'completed', 'completed_at' => now()->subDays(30 - $i * 5)]
                    );
                } elseif ($i === 4) {
                    FormalizationProgress::updateOrCreate(
                        ['user_id' => $aminata->id, 'step_id' => $stepId],
                        ['status' => 'in_progress', 'notes' => 'RDV à la DGID lundi prochain.']
                    );
                }
            }
        }
    }
}
