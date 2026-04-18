<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Sdg;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;

class SectorsAndSdgsSeeder extends Seeder
{
    public function run(): void
    {
        // 17 UN Sustainable Development Goals
        $sdgs = [
            [1,  'Pas de pauvreté',                  '#e5243b'],
            [2,  'Faim « zéro »',                    '#dda63a'],
            [3,  'Bonne santé et bien-être',         '#4c9f38'],
            [4,  'Éducation de qualité',             '#c5192d'],
            [5,  'Égalité entre les sexes',          '#ff3a21'],
            [6,  'Eau propre et assainissement',     '#26bde2'],
            [7,  'Énergie propre et abordable',      '#fcc30b'],
            [8,  'Travail décent et croissance',     '#a21942'],
            [9,  'Industrie, innovation & infra.',   '#fd6925'],
            [10, 'Inégalités réduites',              '#dd1367'],
            [11, 'Villes et communautés durables',   '#fd9d24'],
            [12, 'Consommation responsable',         '#bf8b2e'],
            [13, 'Mesures de lutte climatique',      '#3f7e44'],
            [14, 'Vie aquatique',                    '#0a97d9'],
            [15, 'Vie terrestre',                    '#56c02b'],
            [16, 'Paix, justice et institutions',    '#00689d'],
            [17, 'Partenariats pour les objectifs',  '#19486a'],
        ];
        foreach ($sdgs as [$n, $name, $color]) {
            Sdg::updateOrCreate(
                ['number' => $n],
                ['name' => $name, 'color' => $color]
            );
        }

        // Sub-categories by sector
        $subs = [
            'agritech' => [
                ['drones-precision', 'Drones & agriculture de précision'],
                ['irrigation-solaire', 'Irrigation solaire'],
                ['chaine-froid', 'Chaîne du froid & conservation'],
                ['agro-transformation', 'Agro-transformation'],
                ['elevage-connecte', 'Élevage connecté'],
            ],
            'fintech' => [
                ['mobile-money', 'Mobile money & paiements'],
                ['remittances', 'Remittances diaspora'],
                ['credit-scoring', 'Crédit & scoring'],
                ['insurtech', 'Insurtech'],
                ['neobanque', 'Néobanque'],
            ],
            'healthtech' => [
                ['telemedecine', 'Télémédecine'],
                ['diagnostic-mobile', 'Diagnostic mobile'],
                ['pharmacie-numerique', 'Pharmacie numérique'],
                ['sante-maternelle', 'Santé maternelle & infantile'],
            ],
            'edtech' => [
                ['e-learning', 'E-learning'],
                ['formation-pro', 'Formation professionnelle'],
                ['alphabetisation', 'Alphabétisation'],
                ['langues-locales', 'Langues locales'],
            ],
            'energie' => [
                ['solaire-residentiel', 'Solaire résidentiel'],
                ['mini-reseaux', 'Mini-réseaux'],
                ['cuisson-propre', 'Cuisson propre'],
                ['mobilite-electrique', 'Mobilité électrique'],
            ],
            'commerce' => [
                ['e-commerce', 'E-commerce'],
                ['logistique-livraison', 'Logistique & livraison'],
                ['marketplace-b2b', 'Marketplace B2B'],
            ],
            'industrie' => [
                ['textile', 'Textile & mode'],
                ['materiaux-construction', 'Matériaux de construction'],
                ['transformation-miniere', 'Transformation minière'],
                ['recyclage', 'Recyclage & économie circulaire'],
            ],
            'tourisme' => [
                ['ecotourisme', 'Écotourisme'],
                ['industries-creatives', 'Industries créatives'],
                ['patrimoine', 'Patrimoine & culture'],
            ],
        ];

        foreach ($subs as $catSlug => $items) {
            $category = Category::where('slug', $catSlug)->first();
            if (!$category) continue;
            foreach ($items as [$slug, $name]) {
                SubCategory::updateOrCreate(
                    ['category_id' => $category->id, 'slug' => $slug],
                    ['name' => $name]
                );
            }
        }
    }
}
