<?php

namespace Database\Seeders;

use App\Models\Training;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrainingsSeeder extends Seeder
{
    public function run(): void
    {
        $instructor = User::where('email', 'mentor@globalafricaplus.com')->first()
            ?? User::query()->first();

        if (!$instructor) {
            $this->command?->warn('TrainingsSeeder: aucun utilisateur — abandon.');
            return;
        }

        $items = [
            [
                'title'             => 'Lever des fonds en Afrique : du seed à la série A',
                'summary'           => 'Stratégies, deck pitch et négociation pour réussir vos tours de table auprès des VCs panafricains et de la diaspora.',
                'description'       => "Ce parcours couvre l'écosystème du capital-risque africain (Partech Africa, TLcom, Norrsken22, AfricInvest), la construction d'un deck investisseur convaincant, la modélisation financière SaaS/marketplace, et la mécanique d'une term sheet (valuation, dilution, liquidation preference). Études de cas : Wave, Flutterwave, MNT-Halan.",
                'category'          => 'finance',
                'level'             => 'intermediate',
                'duration_minutes'  => 240,
                'curriculum'        => [
                    "Module 1 — Cartographie des fonds VC en Afrique (45 min)",
                    "Module 2 — Anatomie d'un deck qui convertit (60 min)",
                    "Module 3 — Modèle financier 5 ans + métriques SaaS (45 min)",
                    "Module 4 — Term sheet & négociation (60 min)",
                    "Module 5 — Closing & post-investissement (30 min)",
                ],
                'price'    => 49.00,
                'currency' => 'EUR',
                'rating_avg' => 4.8,
                'purchases_count' => 127,
            ],
            [
                'title'             => 'Mobile Money & Open Banking en zone UEMOA',
                'summary'           => 'Maîtriser Wave, Orange Money, MTN MoMo et les API d\'agrégateurs (PayDunya, Flutterwave, Hub2) pour bâtir des produits fintech.',
                'description'       => "Tour d'horizon technique et réglementaire des paiements mobiles en Afrique de l'Ouest. Architecture d'intégration, gestion des webhooks IPN, conformité BCEAO, KYC/AML, et patterns de réconciliation. Inclut un projet pratique : intégrer PayDunya dans une app Laravel.",
                'category'          => 'tech',
                'level'             => 'intermediate',
                'duration_minutes'  => 300,
                'curriculum'        => [
                    "Module 1 — Panorama des opérateurs (Wave, OM, MTN MoMo, Free Money)",
                    "Module 2 — API REST des agrégateurs (PayDunya, Flutterwave, Hub2)",
                    "Module 3 — Webhooks IPN & sécurité HMAC",
                    "Module 4 — KYC/AML et conformité BCEAO",
                    "Module 5 — Projet : intégration PayDunya en Laravel",
                ],
                'price'    => 39.00,
                'currency' => 'EUR',
                'rating_avg' => 4.9,
                'purchases_count' => 213,
            ],
            [
                'title'             => 'Créer son entreprise au Sénégal : guide pratique',
                'summary'           => 'De l\'immatriculation OHADA aux obligations fiscales : le parcours complet pour formaliser votre activité.',
                'description'       => "Démystifie l'écosystème entrepreneurial sénégalais : choix de la forme juridique (SARL, SA, SUARL, GIE), procédure CFE, obtention du NINEA et du registre du commerce, régime fiscal CGU vs réel, déclarations IPRES/CSS. Bonus : fiscalité de la diaspora et incitations APIX.",
                'category'          => 'entrepreneurship',
                'level'             => 'beginner',
                'duration_minutes'  => 180,
                'curriculum'        => [
                    "Module 1 — Choix de la forme juridique (OHADA)",
                    "Module 2 — Démarches CFE, NINEA, RCCM",
                    "Module 3 — Fiscalité : CGU, TVA, IS",
                    "Module 4 — Déclarations sociales (IPRES, CSS)",
                    "Module 5 — Incitations APIX & fiscalité diaspora",
                ],
                'price'    => 25.00,
                'currency' => 'EUR',
                'rating_avg' => 4.7,
                'purchases_count' => 342,
            ],
            [
                'title'             => 'Leadership pour dirigeants africains',
                'summary'           => 'Bâtir une équipe haute-performance dans un contexte multiculturel et résilient.',
                'description'       => "Conçu pour les CEO et top managers de scale-ups africaines. Couvre les frameworks de management adaptés (OKR, Radical Candor), la gestion du remote multi-pays, la culture d'équipe interculturelle, et le leadership en période de crise (devises, instabilité, supply chain).",
                'category'          => 'leadership',
                'level'             => 'advanced',
                'duration_minutes'  => 210,
                'curriculum'        => [
                    "Module 1 — Définir sa vision & ses OKR",
                    "Module 2 — Recruter et fidéliser dans un marché tendu",
                    "Module 3 — Management remote multi-pays",
                    "Module 4 — Culture d'équipe interculturelle",
                    "Module 5 — Leadership en période de crise",
                ],
                'price'    => 79.00,
                'currency' => 'EUR',
                'rating_avg' => 4.6,
                'purchases_count' => 58,
            ],
            [
                'title'             => 'Growth marketing pour startups B2C africaines',
                'summary'           => 'Acquisition, activation, rétention : les leviers qui marchent vraiment sur le marché africain.',
                'description'       => "Atelier orienté pratique : SEO local, paid social (Facebook, TikTok, YouTube), WhatsApp marketing, programmes de parrainage adaptés au cash, et content marketing en langues locales. Études de cas : Jumia, Glovo Afrique, Yango, Kuda.",
                'category'          => 'marketing',
                'level'             => 'beginner',
                'duration_minutes'  => 195,
                'curriculum'        => [
                    "Module 1 — Funnel AARRR appliqué à l'Afrique",
                    "Module 2 — Paid social : Facebook, TikTok, YouTube Africa",
                    "Module 3 — WhatsApp marketing & community building",
                    "Module 4 — Programmes de parrainage cash-friendly",
                    "Module 5 — Content marketing multilingue",
                ],
                'price'    => 29.00,
                'currency' => 'EUR',
                'rating_avg' => 4.5,
                'purchases_count' => 174,
            ],
        ];

        foreach ($items as $data) {
            Training::updateOrCreate(
                ['title' => $data['title']],
                array_merge($data, [
                    'user_id'      => $instructor->id,
                    'is_published' => true,
                ]),
            );
        }

        $this->command?->info('TrainingsSeeder : ' . count($items) . ' formations seedées.');
    }
}
