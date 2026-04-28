<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\EscrowMilestone;
use App\Models\Investment;
use App\Models\PaymentLog;
use App\Models\Project;
use App\Models\SubCategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds a fully-played-out escrow scenario for the demo accounts:
 *
 *  - Project   : "Système complet de gestion de clinique" (porté par Aminata)
 *  - Investment: 1 000 000 XOF par Ibrahim, statut escrow
 *  - Milestones: jalon #1 approuvé, jalon #2 rejeté, jalon #3 pending
 *
 * Idempotent — re-runnable without duplicating rows.
 */
class DemoEscrowSeeder extends Seeder
{
    public function run(): void
    {
        $aminata = User::where('email', 'aminata@africaplus.test')->first();
        $ibrahim = User::where('email', 'ibrahim@africaplus.test')->first();

        if (!$aminata || !$ibrahim) {
            $this->command?->warn('DemoEscrowSeeder : aminata/ibrahim absents — abandon.');
            return;
        }

        // Resolve category/sub-category by slug to stay portable across envs.
        $category    = Category::where('slug', 'tech')->first() ?? Category::query()->first();
        $subCategory = SubCategory::where('slug', 'saas')->first() ?? SubCategory::query()->first();

        $project = Project::updateOrCreate(
            ['slug' => 'systeme-complet-de-gestion-de-clinique'],
            [
                'user_id'         => $aminata->id,
                'category_id'     => $category?->id,
                'sub_category_id' => $subCategory?->id,
                'title'           => 'Système complet de gestion de clinique',
                'summary'         => "La gestion d'une clinique ou d'un centre de diagnostic exige un système performant pour optimiser les opérations, gérer les patients et traiter efficacement les rendez-vous. Le système de gestion de clinique est une solution logicielle complète conçue pour les cliniques, hôpitaux et centres de diagnostic de petite et moyenne taille.",
                'description'     => $this->fullDescription(),
                'country'         => 'Sénégal',
                'city'            => 'Dakar',
                'amount_needed'   => 25000000,
                'amount_raised'   => 1000000,
                'currency'        => 'XOF',
                'payout_phone'    => '+221774391398',
                'payout_provider' => 'orange-money-senegal',
                'payout_country'  => 'SN',
                'stage'           => 'mvp',
                'status'          => 'published',
                'jobs_target'     => 10,
                'tags'            => [],
                'deadline'        => Carbon::create(2026, 12, 20),
                'published_at'    => Carbon::create(2026, 4, 25, 21, 30, 42),
            ]
        );

        // Underlying transaction (sandbox PayDunya record).
        $transaction = Transaction::updateOrCreate(
            ['paydunya_token' => 'test_sNpMuSgZ4A'],
            [
                'user_id'           => $ibrahim->id,
                'transactable_type' => Project::class,
                'transactable_id'   => $project->id,
                'amount'            => 1000000,
                'currency'          => 'XOF',
                'gateway'           => 'paydunya',
                'gateway_reference' => 'inv_uEtAPx854SSRmldMngnZ',
                'paydunya_invoice_url'  => 'https://paydunya.com/sandbox-checkout/invoice/test_sNpMuSgZ4A',
                'paydunya_receipt_url'  => 'https://paydunya.com/sandbox-checkout/receipt/pdf/test_sNpMuSgZ4A.pdf',
                'payment_type'      => 'investment',
                'status'            => 'completed',
                'customer_name'     => $ibrahim->name,
                'customer_email'    => $ibrahim->email,
                'customer_phone'    => $ibrahim->phone,
                'customer_country'  => 'SN',
                'description'       => "Investissement — {$project->title}",
                'paid_at'           => Carbon::create(2026, 4, 25, 21, 38, 52),
                'custom_data'       => [
                    'project_id'      => $project->id,
                    'project_slug'    => $project->slug,
                    'investment_type' => 'loan',
                    'native_amount'   => 1000000,
                    'native_currency' => 'XOF',
                ],
            ]
        );

        $investment = Investment::updateOrCreate(
            ['transaction_id' => $transaction->id],
            [
                'project_id'         => $project->id,
                'investor_id'        => $ibrahim->id,
                'amount'             => 1000000,
                'charged_amount'     => 1000000,
                'charged_currency'   => 'XOF',
                'currency'           => 'XOF',
                'type'               => 'loan',
                'status'             => 'escrow',
                'paid_at'            => Carbon::create(2026, 4, 25, 21, 38, 52),
                'payment_provider'   => 'paydunya',
                'provider_reference' => 'inv_uEtAPx854SSRmldMngnZ',
                'paydunya_token'     => 'test_sNpMuSgZ4A',
                'paydunya_receipt_url' => 'https://paydunya.com/sandbox-checkout/receipt/pdf/test_sNpMuSgZ4A.pdf',
            ]
        );

        $evidenceFiles = [
            'notes' => 'vous trouverez dans le lien ci-dessous, les documents de conception de la plateforme à savoir: conception & résumé de ce qui a été livré, Document_Technique_v1_1, checklist complète pour intégrer PayDunya, Enquete_Etude_Marche_v1 et le lien de la plateforme web.',
            'urls'  => [
                'https://drive.google.com/drive/folders/1vgjDODLJ0xrj80tAskYdDvuoE5Y1SJXh',
                'https://globalafricaplus.com/',
            ],
        ];

        $milestones = [
            [
                'position'    => 1,
                'title'       => 'Démarrage du projet',
                'amount'      => 400000,
                'percentage'  => 40,
                'status'      => 'approved',
                'due_at'      => Carbon::create(2026, 5, 25),
                'approved_at' => Carbon::create(2026, 4, 25, 22, 23, 28),
                'evidence'    => $evidenceFiles,
                'admin_notes' => null,
            ],
            [
                'position'    => 2,
                'title'       => 'Mi-parcours',
                'amount'      => 400000,
                'percentage'  => 40,
                'status'      => 'rejected',
                'due_at'      => Carbon::create(2026, 7, 25),
                'approved_at' => null,
                'evidence'    => $evidenceFiles,
                'admin_notes' => 'Il manque les documents de ROI des premiers mois lier à votre activité',
            ],
            [
                'position'    => 3,
                'title'       => 'Livraison finale',
                'amount'      => 200000,
                'percentage'  => 20,
                'status'      => 'pending',
                'due_at'      => Carbon::create(2026, 10, 25),
                'approved_at' => null,
                'evidence'    => null,
                'admin_notes' => null,
            ],
        ];

        foreach ($milestones as $m) {
            EscrowMilestone::updateOrCreate(
                [
                    'investment_id' => $investment->id,
                    'position'      => $m['position'],
                ],
                array_merge($m, [
                    'project_id' => $project->id,
                    'currency'   => 'XOF',
                ])
            );
        }

        $this->command?->info('DemoEscrowSeeder : projet "' . $project->title . '" + investissement Ibrahim + 3 jalons seedés.');
    }

    protected function fullDescription(): string
    {
        return "Aperçu du système de gestion de clinique\nLe système de gestion de clinique est une plateforme performante pour la gestion des opérations cliniques. Il centralise les dossiers patients, la prise de rendez-vous, la gestion des ordonnances et le traitement des paiements, offrant ainsi une expérience utilisateur optimale aux administrateurs comme aux patients.\n\nConçu avec une interface conviviale et un design adaptatif, le système s'adresse aux cliniques de toutes tailles, leur permettant de fournir de meilleurs services de santé tout en optimisant leurs flux de travail administratifs.\n\nSystème de gestion de clinique\nCe projet de système de gestion de clinique est destiné aux cliniques, centres de diagnostic et hôpitaux de petite et moyenne taille. Il est utile aux médecins, aux hôpitaux et aux cliniques. Il permet également la mise en place d'un système de prescription dynamique. Développé en PHP avec le framework CodeIgniter.\n\nCaractéristiques clés du système de gestion de clinique\nLe système offre un large éventail de fonctionnalités pour faciliter les tâches cliniques et administratives :\n\n1. Vue d'ensemble du tableau de bord\nFournit un aperçu des performances de la clinique, notamment en ce qui concerne les rendez-vous, les patients et les finances.\n2. Gestion de la relation médecin-patient\nAjoutez et gérez les profils des médecins, leurs spécialités et leurs disponibilités.\nTenir des dossiers patients détaillés, comprenant les informations personnelles, les antécédents médicaux et les ordonnances.\n3. Planification des rendez-vous\nCréez, gérez et consultez vos rendez-vous de manière dynamique.\nÉvitez les conflits d'horaires grâce à des vérifications de disponibilité en temps réel.\n4. Gestion des ordonnances\nGénérer et stocker des ordonnances numériques.\nJoindre les détails de l'ordonnance aux dossiers des patients pour un accès facile.\n5. Gestion des paiements et des factures\nGénérer et suivre les factures des consultations et des traitements.\nAcceptez les paiements en ligne et hors ligne grâce à l'intégration d'une passerelle de paiement.\n6. Gestion des médicaments et des plantes médicinales\nAjouter et catégoriser les médicaments et les plantes médicinales pour les plans de traitement.\nTenir un inventaire avec des classifications détaillées et un historique d'utilisation.\n7. Prise en charge multilingue\nPersonnalisez le système pour qu'il prenne en charge différentes langues, améliorant ainsi l'accessibilité pour les utilisateurs du monde entier.\n8. Intégration de la passerelle SMS\nInformer les patients de leurs prochains rendez-vous et des paiements en attente.\nUtilisez les alertes SMS pour améliorer l'efficacité de la communication.\n9. Gestion des maladies et de la classification\nOrganiser et suivre les maladies et les traitements par catégorie.\nAssocier les classifications aux diagnostics des patients pour une rédaction structurée des rapports.\n10. Contrôle d'accès basé sur les rôles\nAttribuez des rôles tels qu'administrateur, médecin ou réceptionniste pour contrôler l'accès au système.\nPile technologique\nLe système de gestion de la clinique est construit à l'aide de technologies modernes afin de garantir sa fiabilité et son évolutivité :\n\nCôté serveur : PHP (framework CodeIgniter) pour une logique serveur structurée et sécurisée.\nInterface utilisateur : HTML, CSS, Bootstrap pour une interface utilisateur réactive.\nBase de données : MySQL pour une gestion efficace des données.\nIntégration : Passerelle SMS et API de paiement pour des fonctionnalités améliorées";
    }
}
