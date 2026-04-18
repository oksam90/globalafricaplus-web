<?php

namespace Database\Seeders;

use App\Models\JobApplication;
use App\Models\Project;
use App\Models\Role;
use App\Models\RoleProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class JobseekerSeeder extends Seeder
{
    public function run(): void
    {
        $jobseekerRole = Role::where('slug', 'jobseeker')->first();

        // ── 3 jobseeker users ──────────────────────────
        $jobseekers = [
            [
                'name' => 'Ousmane Traoré',
                'email' => 'ousmane@africaplus.test',
                'country' => 'Sénégal',
                'city' => 'Dakar',
                'kyc_level' => 'verified',
                'bio' => 'Développeur full-stack passionné par l\'agritech et les solutions mobiles.',
                'profile' => [
                    'headline' => 'Développeur Full-Stack · 6 ans d\'expérience',
                    'desired_roles' => ['Lead développeur', 'CTO', 'Architecte logiciel'],
                    'experience_years' => 6,
                    'availability' => 'immediate',
                    'cv_url' => 'https://example.com/cv-ousmane.pdf',
                    'open_to_remote' => true,
                    'education' => 'Master en Informatique — Université Cheikh Anta Diop',
                    'languages' => ['français', 'anglais', 'wolof'],
                    'certifications' => ['AWS Certified Solutions Architect', 'Scrum Master'],
                    'preferred_countries' => ['Sénégal', 'Côte d\'Ivoire', 'Rwanda'],
                    'linkedin_url' => 'https://linkedin.com/in/ousmane-traore',
                    'bio' => 'Passionné par l\'innovation africaine, je combine expertise technique et connaissance du terrain.',
                ],
                'skills' => [
                    'developpement-web' => ['level' => 'expert', 'years' => 6],
                    'gestion-projet' => ['level' => 'advanced', 'years' => 3],
                    'design' => ['level' => 'intermediate', 'years' => 2],
                ],
            ],
            [
                'name' => 'Aïcha Konaté',
                'email' => 'aicha@africaplus.test',
                'country' => 'Côte d\'Ivoire',
                'city' => 'Abidjan',
                'kyc_level' => 'verified',
                'bio' => 'Experte en marketing digital et stratégie de croissance.',
                'profile' => [
                    'headline' => 'Marketing Manager · Spécialiste Growth · 8 ans',
                    'desired_roles' => ['Head of Marketing', 'Growth Manager', 'CMO'],
                    'experience_years' => 8,
                    'availability' => '1_month',
                    'open_to_remote' => true,
                    'education' => 'MBA Marketing — ESSEC Abidjan',
                    'languages' => ['français', 'anglais', 'dioula'],
                    'certifications' => ['Google Analytics Certified', 'HubSpot Inbound Marketing'],
                    'preferred_countries' => ['Côte d\'Ivoire', 'Sénégal', 'Ghana'],
                    'bio' => 'Spécialiste du marketing digital en Afrique de l\'Ouest avec une forte expérience en startups.',
                ],
                'skills' => [
                    'marketing-digital' => ['level' => 'expert', 'years' => 8],
                    'gestion-projet' => ['level' => 'advanced', 'years' => 5],
                    'finance' => ['level' => 'intermediate', 'years' => 3],
                ],
            ],
            [
                'name' => 'Jean-Baptiste Mugisha',
                'email' => 'jbaptiste@africaplus.test',
                'country' => 'Rwanda',
                'city' => 'Kigali',
                'kyc_level' => 'basic',
                'bio' => 'Jeune ingénieur agronome cherchant à contribuer à la révolution verte.',
                'profile' => [
                    'headline' => 'Ingénieur Agronome · Spécialiste Agriculture de Précision',
                    'desired_roles' => ['Agronome terrain', 'Chef de projet agricole', 'Consultant agritech'],
                    'experience_years' => 3,
                    'availability' => 'immediate',
                    'open_to_remote' => false,
                    'education' => 'Licence en Agronomie — Université du Rwanda',
                    'languages' => ['français', 'anglais', 'kinyarwanda'],
                    'preferred_countries' => ['Rwanda', 'Burundi', 'Kenya'],
                    'bio' => 'Passionné par les technologies agricoles et le développement durable en Afrique de l\'Est.',
                ],
                'skills' => [
                    'agronomie' => ['level' => 'advanced', 'years' => 3],
                    'gestion-projet' => ['level' => 'intermediate', 'years' => 2],
                ],
            ],
        ];

        $createdUsers = [];

        foreach ($jobseekers as $js) {
            $user = User::updateOrCreate(
                ['email' => $js['email']],
                [
                    'name' => $js['name'],
                    'password' => Hash::make('password'),
                    'country' => $js['country'],
                    'city' => $js['city'],
                    'kyc_level' => $js['kyc_level'],
                    'bio' => $js['bio'],
                ]
            );
            $user->assignRole('jobseeker');

            // Profile data
            $profile = RoleProfile::where('user_id', $user->id)
                ->where('role_id', $jobseekerRole->id)
                ->first();
            if ($profile) {
                $profile->data = $js['profile'];
                $profile->setRelation('role', $jobseekerRole);
                $profile->recomputeCompletion();
                $profile->save();
            }

            // Skills
            $skillSync = [];
            foreach ($js['skills'] as $slug => $data) {
                $skill = Skill::where('slug', $slug)->first();
                if ($skill) {
                    $skillSync[$skill->id] = [
                        'level' => $data['level'],
                        'years_experience' => $data['years'],
                    ];
                }
            }
            $user->skills()->sync($skillSync);

            $createdUsers[] = $user;
        }

        // ── Job applications to existing projects ──────
        $projects = Project::published()->where('jobs_target', '>', 0)->get();

        $applicationTemplates = [
            [
                'role_applied' => 'Lead développeur',
                'motivation' => "Passionné par l'agritech, je souhaite mettre mes compétences en développement full-stack au service de ce projet innovant. Mon expérience avec les architectures cloud et les solutions mobiles serait un atout majeur pour l'équipe.",
                'proposal' => "Je propose de mettre en place une architecture microservices avec API REST, une application mobile React Native pour les agriculteurs, et un dashboard temps réel pour le monitoring des drones.",
                'status' => 'under_review',
            ],
            [
                'role_applied' => 'Responsable Marketing',
                'motivation' => "Avec 8 ans d'expérience en marketing digital en Afrique de l'Ouest, je peux construire une stratégie de croissance adaptée au marché local et à la diaspora. Mon réseau et ma connaissance des canaux africains seront déterminants.",
                'status' => 'shortlisted',
                'score' => 82,
            ],
            [
                'role_applied' => 'Agronome terrain',
                'motivation' => "En tant qu'ingénieur agronome spécialisé en agriculture de précision, je suis convaincu que ce projet peut transformer les pratiques agricoles au Rwanda. Je souhaite contribuer sur le terrain auprès des coopératives.",
                'status' => 'submitted',
            ],
            [
                'role_applied' => 'Growth Manager',
                'motivation' => "Le modèle pay-as-you-go est révolutionnaire pour l'Afrique. Mon expérience en growth hacking et mobile money me permettrait d'accélérer l'adoption dans de nouveaux marchés.",
                'status' => 'accepted',
                'score' => 91,
                'review_notes' => 'Excellent profil, expérience très pertinente. Convoquée pour un entretien final.',
            ],
        ];

        $appIdx = 0;
        foreach ($projects as $project) {
            foreach ($createdUsers as $i => $user) {
                // Each user applies to each project (but only if unique combo)
                if (JobApplication::where('user_id', $user->id)->where('project_id', $project->id)->exists()) {
                    continue;
                }

                $tpl = $applicationTemplates[$appIdx % count($applicationTemplates)];
                JobApplication::create([
                    'user_id' => $user->id,
                    'project_id' => $project->id,
                    'role_applied' => $tpl['role_applied'],
                    'motivation' => $tpl['motivation'],
                    'proposal' => $tpl['proposal'] ?? null,
                    'status' => $tpl['status'],
                    'score' => $tpl['score'] ?? null,
                    'review_notes' => $tpl['review_notes'] ?? null,
                    'reviewed_at' => in_array($tpl['status'], ['submitted']) ? null : now()->subDays(rand(1, 5)),
                ]);
                $appIdx++;
            }
        }
    }
}
