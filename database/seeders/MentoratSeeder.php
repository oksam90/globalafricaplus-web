<?php

namespace Database\Seeders;

use App\Models\MentorAvailability;
use App\Models\Mentorship;
use App\Models\MentorshipSession;
use App\Models\MentorReview;
use App\Models\Role;
use App\Models\RoleProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MentoratSeeder extends Seeder
{
    public function run(): void
    {
        // ── Enrichir les compétences ──
        $skills = [
            // Tech
            ['slug' => 'mobile-dev',         'name' => 'Développement mobile',        'category' => 'Tech'],
            ['slug' => 'data-science',       'name' => 'Data Science & IA',           'category' => 'Tech'],
            ['slug' => 'devops',             'name' => 'DevOps & Cloud',              'category' => 'Tech'],
            ['slug' => 'cybersecurite',      'name' => 'Cybersécurité',               'category' => 'Tech'],
            ['slug' => 'blockchain',         'name' => 'Blockchain & Web3',           'category' => 'Tech'],
            // Business
            ['slug' => 'strategie',          'name' => 'Stratégie d\'entreprise',     'category' => 'Business'],
            ['slug' => 'levee-fonds',        'name' => 'Levée de fonds',              'category' => 'Business'],
            ['slug' => 'business-plan',      'name' => 'Business plan & pitch',       'category' => 'Business'],
            ['slug' => 'vente-b2b',          'name' => 'Vente B2B',                   'category' => 'Business'],
            ['slug' => 'growth-hacking',     'name' => 'Growth hacking',              'category' => 'Business'],
            // Finance
            ['slug' => 'comptabilite-ohada', 'name' => 'Comptabilité OHADA',          'category' => 'Finance'],
            ['slug' => 'microfinance',       'name' => 'Microfinance',                'category' => 'Finance'],
            ['slug' => 'fiscalite-afrique',  'name' => 'Fiscalité africaine',         'category' => 'Finance'],
            // Sectoriel
            ['slug' => 'energie-solaire',    'name' => 'Énergie solaire',             'category' => 'Énergie'],
            ['slug' => 'agroprocessing',     'name' => 'Agro-transformation',         'category' => 'Agritech'],
            ['slug' => 'sante-digitale',     'name' => 'Santé digitale (eHealth)',    'category' => 'Healthtech'],
            ['slug' => 'mobile-money',       'name' => 'Mobile money & paiements',    'category' => 'Fintech'],
            ['slug' => 'logistique',         'name' => 'Logistique & supply chain',   'category' => 'Commerce'],
            // Soft skills
            ['slug' => 'leadership',         'name' => 'Leadership & management',     'category' => 'Soft Skills'],
            ['slug' => 'communication',      'name' => 'Communication & prise de parole', 'category' => 'Soft Skills'],
            ['slug' => 'negociation',        'name' => 'Négociation',                 'category' => 'Soft Skills'],
            // Juridique
            ['slug' => 'droit-ohada',        'name' => 'Droit OHADA',                 'category' => 'Juridique'],
            ['slug' => 'propriete-intell',   'name' => 'Propriété intellectuelle',    'category' => 'Juridique'],
        ];

        foreach ($skills as $s) {
            Skill::updateOrCreate(['slug' => $s['slug']], $s);
        }

        // ── Mentors de démo ──
        $mentorsData = [
            [
                'name' => 'Oumar Diallo',
                'email' => 'oumar@africaplus.test',
                'country' => 'Sénégal',
                'city' => 'Dakar',
                'bio' => 'Serial entrepreneur, fondateur de 3 startups tech au Sénégal. +15 ans d\'expérience en stratégie digitale et levée de fonds. Ex-directeur Sonatel Digital.',
                'is_diaspora' => false,
                'skills' => ['strategie', 'levee-fonds', 'mobile-money', 'leadership'],
                'profile_data' => ['expertise' => 'Stratégie digitale, levée de fonds, mobile money', 'availability_hours_week' => 6, 'languages' => ['Français', 'Wolof', 'Anglais']],
            ],
            [
                'name' => 'Aïcha Benali',
                'email' => 'aicha@africaplus.test',
                'country' => 'Maroc',
                'city' => 'Casablanca',
                'bio' => 'Experte fintech et microfinance. Directrice innovation chez une banque panafricaine. Accompagne les startups dans leur scale-up vers l\'Afrique francophone.',
                'is_diaspora' => false,
                'skills' => ['microfinance', 'fintech' => 'mobile-money', 'fiscalite-afrique', 'business-plan'],
                'profile_data' => ['expertise' => 'Fintech, microfinance, scaling Afrique', 'availability_hours_week' => 4, 'languages' => ['Français', 'Arabe', 'Anglais']],
            ],
            [
                'name' => 'Kwame Asante',
                'email' => 'kwame@africaplus.test',
                'country' => 'Ghana',
                'city' => 'Accra',
                'bio' => 'CTO et architecte cloud avec +12 ans d\'expérience. Expert en développement mobile, data science et DevOps. Mentor à iHub Nairobi et Mest Accra.',
                'is_diaspora' => false,
                'skills' => ['mobile-dev', 'data-science', 'devops', 'cybersecurite'],
                'profile_data' => ['expertise' => 'Cloud architecture, mobile dev, data science', 'availability_hours_week' => 8, 'languages' => ['English', 'Twi']],
            ],
            [
                'name' => 'Marie-Claire Kabongo',
                'email' => 'marie@africaplus.test',
                'country' => 'RDC',
                'city' => 'Kinshasa',
                'bio' => 'Avocate d\'affaires spécialisée OHADA. +10 ans en droit des sociétés et propriété intellectuelle. Accompagne les entrepreneurs dans la structuration juridique.',
                'is_diaspora' => false,
                'skills' => ['droit-ohada', 'propriete-intell', 'negociation'],
                'profile_data' => ['expertise' => 'Droit OHADA, PI, structuration juridique', 'availability_hours_week' => 5, 'languages' => ['Français', 'Lingala', 'Anglais']],
            ],
            [
                'name' => 'Youssef El Amrani',
                'email' => 'youssef@africaplus.test',
                'country' => 'France',
                'city' => 'Paris',
                'bio' => 'Diaspora marocaine. VP Growth chez une licorne française. Expert en growth hacking, vente B2B et marketing digital pour les marchés émergents.',
                'is_diaspora' => true,
                'residence_country' => 'France',
                'skills' => ['growth-hacking', 'vente-b2b', 'marketing-digital', 'communication'],
                'profile_data' => ['expertise' => 'Growth hacking, sales B2B, marketing digital', 'availability_hours_week' => 3, 'languages' => ['Français', 'Arabe', 'Anglais']],
            ],
            [
                'name' => 'Amina Toure',
                'email' => 'amina.toure@africaplus.test',
                'country' => "Côte d'Ivoire",
                'city' => 'Abidjan',
                'bio' => 'Ingénieure agronome et experte en agro-transformation. A accompagné +50 coopératives agricoles. Spécialiste chaîne de valeur cacao et cajou.',
                'is_diaspora' => false,
                'skills' => ['agronomie', 'agroprocessing', 'logistique', 'gestion-projet'],
                'profile_data' => ['expertise' => 'Agro-transformation, chaîne de valeur, coopératives', 'availability_hours_week' => 6, 'languages' => ['Français', 'Dioula']],
            ],
        ];

        $mentorRole = Role::where('slug', 'mentor')->first();

        foreach ($mentorsData as $md) {
            $skillSlugs = [];
            foreach ($md['skills'] as $k => $v) {
                $skillSlugs[] = is_string($k) ? $v : $v;
            }
            unset($md['skills']);
            $profileData = $md['profile_data'];
            unset($md['profile_data']);

            $user = User::updateOrCreate(
                ['email' => $md['email']],
                array_merge($md, [
                    'password' => Hash::make('password'),
                    'kyc_level' => 'verified',
                    'preferred_language' => 'fr',
                ])
            );

            $user->assignRole('mentor');

            // Attach skills
            $skillIds = Skill::whereIn('slug', $skillSlugs)->pluck('id');
            $syncData = [];
            foreach ($skillIds as $sid) {
                $syncData[$sid] = [
                    'level' => collect(['intermediate', 'advanced', 'expert'])->random(),
                    'years_experience' => rand(3, 15),
                ];
            }
            $user->skills()->syncWithoutDetaching($syncData);

            // Update role profile
            $rp = RoleProfile::where('user_id', $user->id)->where('role_id', $mentorRole->id)->first();
            if ($rp) {
                $rp->update(['data' => $profileData]);
                $rp->recomputeCompletion();
                $rp->save();
            }

            // Add availabilities
            if ($user->mentorAvailabilities()->count() === 0) {
                $days = collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
                    ->random(rand(2, 4));
                foreach ($days as $day) {
                    MentorAvailability::create([
                        'user_id' => $user->id,
                        'day_of_week' => $day,
                        'start_time' => collect(['09:00', '10:00', '14:00'])->random(),
                        'end_time' => collect(['12:00', '13:00', '17:00', '18:00'])->random(),
                        'timezone' => 'Africa/Dakar',
                        'is_active' => true,
                    ]);
                }
            }
        }

        // Also give existing Fatou Ndiaye some skills
        $fatou = User::where('email', 'fatou@africaplus.test')->first();
        if ($fatou) {
            $fatouSkills = Skill::whereIn('slug', ['strategie', 'marketing-digital', 'communication', 'growth-hacking'])->pluck('id');
            $syncData = [];
            foreach ($fatouSkills as $sid) {
                $syncData[$sid] = ['level' => 'expert', 'years_experience' => 10];
            }
            $fatou->skills()->syncWithoutDetaching($syncData);

            // Update her profile
            $rp = RoleProfile::where('user_id', $fatou->id)->where('role_id', $mentorRole->id)->first();
            if ($rp) {
                $rp->update(['data' => ['expertise' => 'Stratégie digitale, marketing, communication', 'availability_hours_week' => 5, 'languages' => ['Français', 'Anglais']]]);
                $rp->recomputeCompletion();
                $rp->save();
            }

            if ($fatou->mentorAvailabilities()->count() === 0) {
                foreach (['tuesday', 'thursday'] as $day) {
                    MentorAvailability::create([
                        'user_id' => $fatou->id,
                        'day_of_week' => $day,
                        'start_time' => '14:00',
                        'end_time' => '17:00',
                        'timezone' => 'Africa/Abidjan',
                        'is_active' => true,
                    ]);
                }
            }
        }

        // ── Demo mentorship (Aminata ↔ Fatou) ──
        $aminata = User::where('email', 'aminata@africaplus.test')->first();
        if ($aminata && $fatou) {
            $strategie = Skill::where('slug', 'strategie')->first();
            $mentorship = Mentorship::updateOrCreate(
                ['mentor_id' => $fatou->id, 'mentee_id' => $aminata->id, 'topic' => 'Stratégie de scaling AgriDrone'],
                [
                    'skill_id' => $strategie?->id,
                    'message' => 'Bonjour Fatou, j\'aimerais être accompagnée sur la stratégie de croissance de mon projet AgriDrone Sahel, notamment pour la levée de fonds série A.',
                    'goals' => 'Préparer un pitch deck solide, identifier les investisseurs cibles, structurer la gouvernance.',
                    'duration_weeks' => 12,
                    'status' => 'accepted',
                    'accepted_at' => now()->subWeeks(4),
                ]
            );

            // Add demo sessions
            if ($mentorship->sessions()->count() === 0) {
                MentorshipSession::create([
                    'mentorship_id' => $mentorship->id,
                    'title' => 'Kickoff — Analyse du projet',
                    'notes' => 'Revue complète du business model, identification des forces et faiblesses.',
                    'mentor_feedback' => 'Très bon potentiel. Le business model est solide, il faut travailler le pitch et la traction.',
                    'scheduled_at' => now()->subWeeks(3),
                    'duration_minutes' => 60,
                    'status' => 'completed',
                ]);

                MentorshipSession::create([
                    'mentorship_id' => $mentorship->id,
                    'title' => 'Pitch deck & storytelling',
                    'notes' => 'Travail sur le pitch deck, structure narrative, données clés à mettre en avant.',
                    'scheduled_at' => now()->subWeeks(1),
                    'duration_minutes' => 90,
                    'status' => 'completed',
                ]);

                MentorshipSession::create([
                    'mentorship_id' => $mentorship->id,
                    'title' => 'Mapping investisseurs',
                    'scheduled_at' => now()->addWeeks(1),
                    'duration_minutes' => 60,
                    'status' => 'scheduled',
                ]);
            }
        }
    }
}
