<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Plans ───────────────────────────────────────────
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Gratuit',
                'subtitle' => 'Pour démarrer et explorer',
                'description' => 'Accédez aux fonctionnalités de base : exploration des projets, consultation des offres, profil basique.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'currency' => 'EUR',
                'features' => [
                    'Profil basique multi-rôles',
                    'Exploration des projets publiés',
                    'Consultation des offres d\'emploi',
                    'Accès aux guides de formalisation',
                    'Portail diaspora (lecture)',
                    'Annuaire des mentors (consultation)',
                ],
                'limits' => [
                    'projects' => 0,
                    'applications_per_month' => 0,
                    'mentorship_requests' => 0,
                    'storage_mb' => 50,
                ],
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'subtitle' => 'Pour les porteurs de projets',
                'description' => 'Publiez vos projets, postulez aux offres d\'emploi et accédez au mentorat.',
                'price_monthly' => 9.99,
                'price_yearly' => 99.99,
                'currency' => 'EUR',
                'features' => [
                    'Tout du plan Gratuit',
                    'Publication de 3 projets max',
                    '10 candidatures / mois',
                    '2 demandes de mentorat / mois',
                    'Suivi de formalisation personnalisé',
                    'Templates business plan',
                    'Alertes emploi par email',
                    'Support par email (48h)',
                ],
                'limits' => [
                    'projects' => 3,
                    'applications_per_month' => 10,
                    'mentorship_requests' => 2,
                    'storage_mb' => 500,
                ],
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'subtitle' => 'Pour les professionnels ambitieux',
                'description' => 'Projets illimités, matching IA, accès prioritaire au mentorat et visibilité renforcée.',
                'price_monthly' => 29.99,
                'price_yearly' => 299.99,
                'currency' => 'EUR',
                'features' => [
                    'Tout du plan Starter',
                    'Projets illimités',
                    'Candidatures illimitées',
                    'Mentorat illimité',
                    'Matching IA avancé',
                    'Accès API partenaires microfinance',
                    'Badge "Pro" sur le profil',
                    'Visibilité prioritaire dans les résultats',
                    'Support prioritaire (24h)',
                    'Analytics avancés sur vos projets',
                ],
                'limits' => [
                    'projects' => -1,
                    'applications_per_month' => -1,
                    'mentorship_requests' => -1,
                    'storage_mb' => 5000,
                ],
                'is_popular' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'subtitle' => 'Pour les organisations et gouvernements',
                'description' => 'Solution complète pour institutions, gouvernements et grandes organisations avec support dédié.',
                'price_monthly' => 79.99,
                'price_yearly' => 799.99,
                'currency' => 'EUR',
                'features' => [
                    'Tout du plan Pro',
                    'Gestion multi-utilisateurs (équipe)',
                    'Appels à projets gouvernementaux',
                    'Zones économiques spéciales (ZES)',
                    'Tableau de bord institutionnel',
                    'API dédiée & webhooks',
                    'Account manager dédié',
                    'Support 24/7 par téléphone',
                    'Formation personnalisée',
                    'SLA garanti (99.9%)',
                    'Export données illimité',
                    'Badge "Vérifié" institutionnel',
                ],
                'limits' => [
                    'projects' => -1,
                    'applications_per_month' => -1,
                    'mentorship_requests' => -1,
                    'storage_mb' => 50000,
                    'team_members' => 25,
                ],
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        // ─── Demo subscriptions for existing test users ─────
        $starterPlan = SubscriptionPlan::where('slug', 'starter')->first();
        $proPlan = SubscriptionPlan::where('slug', 'pro')->first();

        // Give Aminata (entrepreneur demo) a Pro subscription
        $aminata = User::where('email', 'aminata@africaplus.test')->first();
        if ($aminata && $proPlan) {
            Subscription::updateOrCreate(
                ['user_id' => $aminata->id, 'status' => 'active'],
                [
                    'plan_id' => $proPlan->id,
                    'billing_cycle' => 'monthly',
                    'starts_at' => now()->subDays(10),
                    'ends_at' => now()->addDays(20),
                    'trial_ends_at' => now()->addDays(20),
                    'payment_method' => 'card',
                ]
            );
        }

        // Give Moussa (investor demo) a Starter subscription
        $moussa = User::where('email', 'moussa@africaplus.test')->first();
        if ($moussa && $starterPlan) {
            Subscription::updateOrCreate(
                ['user_id' => $moussa->id, 'status' => 'active'],
                [
                    'plan_id' => $starterPlan->id,
                    'billing_cycle' => 'yearly',
                    'starts_at' => now()->subDays(5),
                    'ends_at' => now()->addYear()->subDays(5),
                    'trial_ends_at' => now()->addDays(25),
                    'payment_method' => 'mobile_money',
                ]
            );
        }
    }
}
