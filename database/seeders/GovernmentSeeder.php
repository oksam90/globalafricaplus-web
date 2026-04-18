<?php

namespace Database\Seeders;

use App\Models\CallApplication;
use App\Models\EconomicZone;
use App\Models\GovernmentCall;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GovernmentSeeder extends Seeder
{
    public function run(): void
    {
        // Create government users
        $govUsers = [];
        $govData = [
            ['name' => 'Min. Économie Sénégal', 'email' => 'eco@gouv.sn', 'country' => 'Sénégal', 'city' => 'Dakar'],
            ['name' => "Min. Industrie Côte d'Ivoire", 'email' => 'industrie@gouv.ci', 'country' => "Côte d'Ivoire", 'city' => 'Abidjan'],
            ['name' => 'Min. TIC Rwanda', 'email' => 'ict@gov.rw', 'country' => 'Rwanda', 'city' => 'Kigali'],
        ];

        foreach ($govData as $g) {
            $user = User::updateOrCreate(
                ['email' => $g['email']],
                array_merge($g, [
                    'password' => Hash::make('password'),
                    'kyc_level' => 'verified',
                    'preferred_language' => 'fr',
                ])
            );
            $user->assignRole('government');
            $govUsers[] = $user;
        }

        // ─── Government Calls ───
        $calls = [
            [
                'user' => 0, 'title' => 'Appel à projets Agritech Sénégal 2026',
                'description' => "Le Ministère de l'Économie du Sénégal lance un appel à projets pour des solutions technologiques innovantes dans le secteur agricole. L'objectif est de moderniser les chaînes de valeur agricoles, réduire les pertes post-récolte et améliorer l'accès aux marchés pour les petits exploitants.",
                'country' => 'Sénégal', 'geographic_zone' => 'Région de Saint-Louis et Casamance',
                'sector' => 'Agritech', 'budget' => 500000, 'currency' => 'EUR',
                'eligibility_criteria' => "- Startup ou PME enregistrée dans un pays africain\n- Solution opérationnelle (MVP minimum)\n- Équipe d'au moins 3 personnes\n- Impact mesurable sur au moins 500 agriculteurs",
                'required_documents' => "- Business plan détaillé\n- Pitch deck\n- Preuves de concept / pilote\n- Registre de commerce\n- CV des fondateurs",
                'evaluation_criteria' => "- Innovation technologique (25%)\n- Impact social et environnemental (30%)\n- Viabilité financière (20%)\n- Capacité d'exécution de l'équipe (15%)\n- Scalabilité panafricaine (10%)",
                'opens_at' => now()->subDays(10)->toDateString(),
                'closes_at' => now()->addDays(50)->toDateString(),
                'status' => 'open',
            ],
            [
                'user' => 0, 'title' => 'Programme d\'incubation numérique Dakar 2026',
                'description' => "Programme d'incubation de 6 mois pour les startups numériques au Sénégal. Financement seed, mentorat, espace de coworking et accès au réseau gouvernemental.",
                'country' => 'Sénégal', 'geographic_zone' => 'Dakar et banlieue',
                'sector' => 'Numérique', 'budget' => 200000, 'currency' => 'EUR',
                'eligibility_criteria' => "- Startup sénégalaise de moins de 3 ans\n- Produit numérique fonctionnel\n- Équipe dédiée à plein temps",
                'required_documents' => "- Pitch deck\n- Démonstration du produit\n- NINEA",
                'evaluation_criteria' => "- Potentiel de marché (30%)\n- Innovation (25%)\n- Équipe (25%)\n- Traction (20%)",
                'opens_at' => now()->subDays(5)->toDateString(),
                'closes_at' => now()->addDays(25)->toDateString(),
                'status' => 'open',
            ],
            [
                'user' => 1, 'title' => "Fonds d'amorçage Fintech Abidjan",
                'description' => "Le Ministère de l'Industrie ivoirien ouvre un fonds dédié aux startups fintech visant l'inclusion financière. Budget de 1M€ réparti entre 10 lauréats.",
                'country' => "Côte d'Ivoire", 'geographic_zone' => 'Abidjan et Grand Abidjan',
                'sector' => 'Fintech', 'budget' => 1000000, 'currency' => 'EUR',
                'eligibility_criteria' => "- Startup fintech opérant en Côte d'Ivoire\n- Licence ou autorisation BCEAO en cours\n- Minimum 1000 utilisateurs actifs",
                'required_documents' => "- Business plan\n- États financiers\n- Licence BCEAO\n- Preuves d'utilisation",
                'evaluation_criteria' => "- Inclusion financière (35%)\n- Modèle économique (25%)\n- Technologie (20%)\n- Équipe (20%)",
                'opens_at' => now()->subDays(20)->toDateString(),
                'closes_at' => now()->addDays(10)->toDateString(),
                'status' => 'open',
            ],
            [
                'user' => 2, 'title' => 'Rwanda Digital Transformation Challenge',
                'description' => 'The Rwanda ICT Ministry invites proposals for digital solutions to transform public services. Focus areas: e-governance, digital identity, smart city infrastructure.',
                'country' => 'Rwanda', 'geographic_zone' => 'National',
                'sector' => 'GovTech', 'budget' => 750000, 'currency' => 'USD',
                'eligibility_criteria' => "- Company registered in Africa\n- Proven track record in e-governance\n- Solution deployable within 6 months",
                'required_documents' => "- Technical proposal\n- Company registration\n- Case studies from previous deployments",
                'evaluation_criteria' => "- Technical fit (30%)\n- Cost effectiveness (25%)\n- Implementation timeline (20%)\n- Sustainability (25%)",
                'opens_at' => now()->subDays(30)->toDateString(),
                'closes_at' => now()->subDays(2)->toDateString(),
                'status' => 'closed',
            ],
            [
                'user' => 0, 'title' => 'Appel Énergie Solaire — Zone rurale',
                'description' => 'Déploiement de solutions solaires décentralisées pour les zones rurales du Sénégal non raccordées au réseau électrique.',
                'country' => 'Sénégal', 'geographic_zone' => 'Kédougou, Tambacounda, Kolda',
                'sector' => 'Énergie', 'budget' => 300000, 'currency' => 'EUR',
                'eligibility_criteria' => "- Expertise en énergie solaire off-grid\n- Expérience en zone rurale africaine",
                'required_documents' => "- Proposition technique et financière\n- Références projets similaires",
                'evaluation_criteria' => "- Impact (40%)\n- Coût par bénéficiaire (30%)\n- Durabilité (30%)",
                'opens_at' => now()->subMonths(3)->toDateString(),
                'closes_at' => now()->subMonth()->toDateString(),
                'status' => 'awarded',
            ],
        ];

        $createdCalls = [];
        foreach ($calls as $c) {
            $userIdx = $c['user'];
            unset($c['user']);
            $c['user_id'] = $govUsers[$userIdx]->id;
            $c['slug'] = GovernmentCall::generateUniqueSlug($c['title']);
            $c['published_at'] = $c['status'] !== 'draft' ? now()->subDays(rand(1, 30)) : null;
            $createdCalls[] = GovernmentCall::updateOrCreate(
                ['slug' => $c['slug']],
                $c
            );
        }

        // ─── Sample applications ───
        $entrepreneurs = User::whereHas('roles', fn ($q) => $q->where('slug', 'entrepreneur'))->limit(5)->get();
        foreach ($entrepreneurs as $i => $ent) {
            foreach ($createdCalls as $j => $call) {
                if ($call->status !== 'open' && $call->status !== 'closed') continue;
                if (rand(0, 1) === 0) continue; // random skip

                CallApplication::updateOrCreate(
                    ['call_id' => $call->id, 'user_id' => $ent->id],
                    [
                        'motivation' => "Notre projet adresse directement les objectifs de cet appel. Nous avons une expérience de {$i} ans dans ce secteur et notre solution a déjà démontré des résultats concrets.",
                        'proposal' => "Nous proposons de déployer notre solution en 3 phases sur 12 mois, avec des KPIs mesurables à chaque étape.",
                        'status' => ['submitted', 'under_review', 'shortlisted'][rand(0, 2)],
                        'score' => rand(0, 1) ? rand(40, 95) : null,
                    ]
                );
                // Update counter
                $call->update(['applications_count' => $call->applications()->count()]);
            }
        }

        // ─── Economic Zones ───
        $zones = [
            [
                'user' => 0, 'name' => 'Parc Industriel de Diamniadio',
                'country' => 'Sénégal', 'region' => 'Dakar',
                'description' => "Zone économique spéciale de 50 hectares dédiée à l'industrie légère, l'agro-transformation et le numérique. Infrastructure moderne, avantages fiscaux pendant 10 ans.",
                'incentives' => ['Exonération IS 10 ans', 'TVA réduite', 'Terrain à prix subventionné', 'Fibre optique incluse'],
                'sectors' => ['Industrie', 'Agro-transformation', 'Numérique'],
                'area_hectares' => 50, 'status' => 'active',
                'website' => 'https://www.diamniadio-park.sn', 'contact_email' => 'invest@diamniadio-park.sn',
            ],
            [
                'user' => 1, 'name' => 'Zone Franche de Grand-Bassam',
                'country' => "Côte d'Ivoire", 'region' => 'Grand-Bassam',
                'description' => "Zone franche industrielle axée sur l'exportation. Avantages douaniers, guichet unique et accès portuaire facilité.",
                'incentives' => ['Franchise douanière totale', 'Guichet unique', 'Accès port autonome'],
                'sectors' => ['Export', 'Textile', 'Agro-industrie'],
                'area_hectares' => 120, 'status' => 'active',
                'contact_email' => 'contact@zf-grandbassam.ci',
            ],
            [
                'user' => 2, 'name' => 'Kigali Innovation City',
                'country' => 'Rwanda', 'region' => 'Kigali',
                'description' => 'Africa\'s first innovation hub dedicated to tech companies. World-class infrastructure, tax incentives and access to pan-African markets.',
                'incentives' => ['0% CIT for 7 years', 'Free trade zone', 'Visa facilitation', 'One-stop shop'],
                'sectors' => ['ICT', 'Fintech', 'EdTech', 'HealthTech'],
                'area_hectares' => 70, 'status' => 'active',
                'website' => 'https://www.kigaliinnovationcity.rw', 'contact_email' => 'info@kic.rw',
            ],
            [
                'user' => 0, 'name' => 'Pôle Agritech de Saint-Louis',
                'country' => 'Sénégal', 'region' => 'Saint-Louis',
                'description' => "Pôle de développement dédié à l'agriculture intelligente et à la transformation des produits du fleuve Sénégal. Phase de planification.",
                'incentives' => ['Subventions R&D', 'Accès à l\'eau irrigable', 'Formation technique'],
                'sectors' => ['Agritech', 'Agroalimentaire'],
                'area_hectares' => 200, 'status' => 'planned',
            ],
        ];

        foreach ($zones as $z) {
            $userIdx = $z['user'];
            unset($z['user']);
            $z['user_id'] = $govUsers[$userIdx]->id;
            $z['slug'] = EconomicZone::generateUniqueSlug($z['name']);
            EconomicZone::updateOrCreate(['slug' => $z['slug']], $z);
        }
    }
}
