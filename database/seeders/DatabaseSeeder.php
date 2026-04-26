<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Project;
use App\Models\Role;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['slug' => 'entrepreneur', 'name' => 'Entrepreneur', 'description' => 'Porteur de projet'],
            ['slug' => 'investor',     'name' => 'Investisseur', 'description' => 'Diaspora ou institutionnel'],
            ['slug' => 'government',   'name' => 'Gouvernement', 'description' => 'Acteur public'],
            ['slug' => 'jobseeker',    'name' => "Chercheur d'emploi", 'description' => "Talent à la recherche d'opportunités"],
            ['slug' => 'mentor',       'name' => 'Mentor', 'description' => 'Expert qui accompagne'],
            ['slug' => 'admin',        'name' => 'Administrateur', 'description' => 'Équipe Africa+'],
        ];
        foreach ($roles as $r) {
            Role::updateOrCreate(['slug' => $r['slug']], $r);
        }

        $categories = [
            ['slug' => 'agritech',     'name' => 'Agritech',          'icon' => 'leaf',    'color' => '#16a34a'],
            ['slug' => 'fintech',      'name' => 'Fintech',           'icon' => 'wallet',  'color' => '#2563eb'],
            ['slug' => 'healthtech',   'name' => 'Healthtech',        'icon' => 'heart',   'color' => '#e11d48'],
            ['slug' => 'edtech',       'name' => 'Edtech',            'icon' => 'book',    'color' => '#7c3aed'],
            ['slug' => 'energie',      'name' => 'Énergie',           'icon' => 'sun',     'color' => '#f59e0b'],
            ['slug' => 'commerce',     'name' => 'Commerce & Retail', 'icon' => 'store',   'color' => '#0ea5e9'],
            ['slug' => 'industrie',    'name' => 'Industrie',         'icon' => 'factory', 'color' => '#64748b'],
            ['slug' => 'tourisme',     'name' => 'Tourisme & Culture','icon' => 'compass', 'color' => '#db2777'],
        ];
        foreach ($categories as $c) {
            Category::updateOrCreate(['slug' => $c['slug']], $c);
        }

        $skills = [
            ['slug' => 'gestion-projet',    'name' => 'Gestion de projet',     'category' => 'Management'],
            ['slug' => 'marketing-digital', 'name' => 'Marketing digital',     'category' => 'Marketing'],
            ['slug' => 'developpement-web', 'name' => 'Développement web',     'category' => 'Tech'],
            ['slug' => 'finance',           'name' => 'Finance & comptabilité','category' => 'Finance'],
            ['slug' => 'agronomie',         'name' => 'Agronomie',             'category' => 'Agritech'],
            ['slug' => 'design',            'name' => 'Design UI/UX',          'category' => 'Tech'],
        ];
        foreach ($skills as $s) {
            Skill::updateOrCreate(['slug' => $s['slug']], $s);
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@africaplus.test'],
            [
                'name' => 'Admin Africa+',
                'password' => Hash::make('password'),
                'country' => 'Sénégal',
                'city' => 'Dakar',
                'kyc_level' => 'certified',
                'preferred_language' => 'fr',
            ]
        );
        $admin->assignRole('admin');

        $entrepreneur = User::updateOrCreate(
            ['email' => 'aminata@africaplus.test'],
            [
                'name' => 'Aminata Diop',
                'password' => Hash::make('password'),
                'country' => 'Sénégal',
                'city' => 'Thiès',
                'kyc_level' => 'verified',
                'bio' => "Fondatrice d'une startup agritech à Thiès.",
            ]
        );
        $entrepreneur->assignRole('entrepreneur');

        $investor = User::updateOrCreate(
            ['email' => 'ibrahim@africaplus.test'],
            [
                'name' => 'Ibrahim Sow',
                'password' => Hash::make('password'),
                'country' => 'France',
                'city' => 'Paris',
                'is_diaspora' => true,
                'residence_country' => 'France',
                'kyc_level' => 'certified',
                'bio' => 'Investisseur diaspora basé à Paris.',
            ]
        );
        $investor->assignRole('investor');

        $mentor = User::updateOrCreate(
            ['email' => 'fatou@africaplus.test'],
            [
                'name' => 'Fatou Ndiaye',
                'password' => Hash::make('password'),
                'country' => "Côte d'Ivoire",
                'city' => 'Abidjan',
                'kyc_level' => 'verified',
                'bio' => 'Consultante senior, mentor en stratégie digitale.',
            ]
        );
        $mentor->assignRole('mentor');

        $agritech = Category::where('slug', 'agritech')->first();
        $energie  = Category::where('slug', 'energie')->first();
        $fintech  = Category::where('slug', 'fintech')->first();

        $demoProjects = [
            [
                'title' => 'AgriDrone Sahel',
                'summary' => "Drones d'épandage solaires pour petits producteurs au Sénégal.",
                'description' => "Nous équipons les coopératives agricoles avec des drones d'épandage à recharge solaire afin de réduire les coûts et d'augmenter les rendements de 30%.",
                'country' => 'Sénégal',
                'city' => 'Thiès',
                'amount_needed' => 75000,
                'amount_raised' => 22500,
                'currency' => 'EUR',
                'stage' => 'mvp',
                'status' => 'published',
                'jobs_target' => 25,
                'category_id' => $agritech?->id,
                'tags' => ['agritech', 'drone', 'solaire'],
            ],
            [
                'title' => 'SolarBox Côte d\'Ivoire',
                'summary' => 'Kits solaires pré-payés pour villages hors réseau.',
                'description' => 'Distribution de kits solaires avec paiement mobile money. Modèle pay-as-you-go.',
                'country' => "Côte d'Ivoire",
                'city' => 'Bouaké',
                'amount_needed' => 120000,
                'amount_raised' => 48000,
                'currency' => 'EUR',
                'stage' => 'launch',
                'status' => 'published',
                'jobs_target' => 40,
                'category_id' => $energie?->id,
                'tags' => ['énergie', 'solaire', 'mobile-money'],
            ],
            [
                'title' => 'PayDiaspo',
                'summary' => "Application de transfert d'argent panafricain à frais réduits.",
                'description' => "Plateforme de remittances entre l'Europe et 12 pays africains avec frais < 1%.",
                'country' => 'Sénégal',
                'city' => 'Dakar',
                'amount_needed' => 250000,
                'amount_raised' => 90000,
                'currency' => 'EUR',
                'stage' => 'scaling',
                'status' => 'published',
                'jobs_target' => 60,
                'category_id' => $fintech?->id,
                'tags' => ['fintech', 'diaspora', 'remittances'],
            ],
        ];

        // Seed sectors (sub-categories), SDGs, and country guides
        $this->call(SectorsAndSdgsSeeder::class);
        $this->call(CountryGuidesSeeder::class);
        $this->call(MentoratSeeder::class);
        $this->call(GovernmentSeeder::class);
        $this->call(JobseekerSeeder::class);
        $this->call(FormalisationSeeder::class);
        $this->call(SubscriptionSeeder::class);
        $this->call(KycSeeder::class);
        $this->call(AdvertisingSeeder::class);
        $this->call(TrainingsSeeder::class);

        foreach ($demoProjects as $data) {
            Project::updateOrCreate(
                ['title' => $data['title']],
                array_merge($data, [
                    'user_id' => $entrepreneur->id,
                    'published_at' => now(),
                    'followers_count' => rand(3, 45),
                ])
            );
        }

        // Attach sub-categories & SDGs to demo projects
        $mapping = [
            'AgriDrone Sahel' => [
                'sub' => 'drones-precision',
                'sdgs' => [2, 8, 13],
            ],
            "SolarBox Côte d'Ivoire" => [
                'sub' => 'solaire-residentiel',
                'sdgs' => [7, 11, 13],
            ],
            'PayDiaspo' => [
                'sub' => 'remittances',
                'sdgs' => [8, 10, 17],
            ],
        ];
        foreach ($mapping as $title => $cfg) {
            $project = Project::where('title', $title)->first();
            if (!$project) continue;
            $sub = \App\Models\SubCategory::where('slug', $cfg['sub'])->first();
            if ($sub) $project->update(['sub_category_id' => $sub->id]);

            $sdgIds = \App\Models\Sdg::whereIn('number', $cfg['sdgs'])->pluck('id')->all();
            $project->sdgs()->sync($sdgIds);
        }

        // Demo project update (news)
        $agriDrone = Project::where('title', 'AgriDrone Sahel')->first();
        if ($agriDrone) {
            \App\Models\ProjectUpdate::updateOrCreate(
                ['project_id' => $agriDrone->id, 'title' => 'Premier vol réussi !'],
                [
                    'user_id' => $entrepreneur->id,
                    'body' => "Nos drones ont effectué leur premier vol d'épandage au-dessus d'une parcelle pilote de 5 hectares. Résultats très encourageants, un gain de temps estimé à 70% par rapport à l'épandage manuel.",
                ]
            );
        }
    }
}
