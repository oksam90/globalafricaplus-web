<?php

namespace Database\Seeders;

use App\Models\AdBanner;
use App\Models\Partner;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class AdvertisingSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Bannières publicitaires (diaporama) ────────────
        $banners = [
            [
                'title' => 'Investissez dans l\'avenir de l\'Afrique',
                'subtitle' => 'Programme d\'accompagnement BAD 2026',
                'description' => 'La Banque Africaine de Développement soutient les PME innovantes à travers Africa+. Bénéficiez de financements allant de 10 000 à 500 000 € pour vos projets à impact.',
                'image_url' => '/images/ads/bad-investment.jpg',
                'cta_text' => 'En savoir plus',
                'cta_url' => '/projets',
                'sponsor' => 'Banque Africaine de Développement',
                'sponsor_logo' => '/images/partners/bad-logo.png',
                'position' => 'home_top',
                'sort_order' => 1,
            ],
            [
                'title' => 'Mobile Money sans frontières',
                'subtitle' => 'Partenariat Globalafrica+ x Wave',
                'description' => 'Transférez et investissez depuis n\'importe où dans le monde avec des frais réduits grâce à notre partenaire Wave. Paiements instantanés dans 8 pays d\'Afrique de l\'Ouest.',
                'image_url' => '/images/ads/wave-partnership.jpg',
                'cta_text' => 'Découvrir',
                'cta_url' => '/diaspora',
                'sponsor' => 'Wave',
                'sponsor_logo' => '/images/partners/wave-logo.png',
                'position' => 'home_top',
                'sort_order' => 2,
            ],
            [
                'title' => 'Formalisez votre entreprise en 72h',
                'subtitle' => 'Nouveau : Hub de formalisation APIX',
                'description' => 'Grâce à notre partenariat avec l\'APIX, créez votre entreprise formelle au Sénégal en seulement 3 jours. Accompagnement complet, de la rédaction des statuts à l\'obtention du RCCM.',
                'image_url' => '/images/ads/apix-formalization.jpg',
                'cta_text' => 'Commencer',
                'cta_url' => '/formalisation',
                'sponsor' => 'APIX Sénégal',
                'sponsor_logo' => '/images/partners/apix-logo.png',
                'position' => 'home_top',
                'sort_order' => 3,
            ],
            [
                'title' => 'Recrutez les meilleurs talents africains',
                'subtitle' => 'Portail emploi Globalafrica+',
                'description' => 'Accédez à un vivier de talents qualifiés issus de 54 pays africains et de la diaspora. Intelligence artificielle de matching pour trouver le profil idéal.',
                'image_url' => '/images/ads/talent-africa.jpg',
                'cta_text' => 'Explorer les talents',
                'cta_url' => '/emploi',
                'sponsor' => 'Globalafrica+',
                'sponsor_logo' => '/images/logo-small.png',
                'position' => 'home_top',
                'sort_order' => 4,
            ],
            [
                'title' => 'Forum des Investisseurs Panafricains 2026',
                'subtitle' => 'Dakar, 15-17 Novembre 2026',
                'description' => 'Le plus grand rassemblement d\'investisseurs, d\'entrepreneurs et de décideurs africains. 3 jours de networking, pitchs et ateliers.',
                'image_url' => '/images/ads/forum-investors.jpg',
                'cta_text' => 'S\'inscrire',
                'cta_url' => '#',
                'sponsor' => 'UEMOA',
                'sponsor_logo' => '/images/partners/uemoa-logo.png',
                'position' => 'home_top',
                'sort_order' => 5,
            ],
        ];

        foreach ($banners as $b) {
            AdBanner::updateOrCreate(['title' => $b['title']], $b);
        }

        // ─── Partenaires (ils nous font confiance) ──────────
        $partners = [
            ['name' => 'Banque Africaine de Développement', 'slug' => 'bad', 'logo_url' => '/images/partners/bad-logo.png', 'website' => 'https://www.afdb.org', 'type' => 'financial', 'description' => 'Première institution de financement du développement en Afrique.', 'sort_order' => 1],
            ['name' => 'BCEAO', 'slug' => 'bceao', 'logo_url' => '/images/partners/bceao-logo.png', 'website' => 'https://www.bceao.int', 'type' => 'financial', 'description' => 'Banque Centrale des États de l\'Afrique de l\'Ouest.', 'sort_order' => 2],
            ['name' => 'UEMOA', 'slug' => 'uemoa', 'logo_url' => '/images/partners/uemoa-logo.png', 'website' => 'https://www.uemoa.int', 'type' => 'institutional', 'description' => 'Union Économique et Monétaire Ouest Africaine.', 'sort_order' => 3],
            ['name' => 'Wave', 'slug' => 'wave', 'logo_url' => '/images/partners/wave-logo.png', 'website' => 'https://www.wave.com', 'type' => 'tech', 'description' => 'Solution de paiement mobile leader en Afrique de l\'Ouest.', 'sort_order' => 4],
            ['name' => 'Orange Money', 'slug' => 'orange-money', 'logo_url' => '/images/partners/orange-money-logo.png', 'website' => 'https://www.orange.com', 'type' => 'tech', 'description' => 'Service de mobile money d\'Orange, présent dans 17 pays africains.', 'sort_order' => 5],
            ['name' => 'Flutterwave', 'slug' => 'flutterwave', 'logo_url' => '/images/partners/flutterwave-logo.png', 'website' => 'https://flutterwave.com', 'type' => 'tech', 'description' => 'Passerelle de paiement panafricaine pour les entreprises.', 'sort_order' => 6],
            ['name' => 'APIX Sénégal', 'slug' => 'apix', 'logo_url' => '/images/partners/apix-logo.png', 'website' => 'https://investinsenegal.com', 'type' => 'government', 'description' => 'Agence nationale pour la Promotion des Investissements.', 'sort_order' => 7],
            ['name' => 'CEPICI', 'slug' => 'cepici', 'logo_url' => '/images/partners/cepici-logo.png', 'website' => 'https://cepici.gouv.ci', 'type' => 'government', 'description' => 'Centre de Promotion des Investissements en Côte d\'Ivoire.', 'sort_order' => 8],
            ['name' => 'Union Africaine', 'slug' => 'ua', 'logo_url' => '/images/partners/ua-logo.png', 'website' => 'https://au.int', 'type' => 'institutional', 'description' => 'Organisation continentale regroupant les 55 États africains.', 'sort_order' => 9],
            ['name' => 'PNUD Afrique', 'slug' => 'pnud', 'logo_url' => '/images/partners/pnud-logo.png', 'website' => 'https://www.undp.org/africa', 'type' => 'ngo', 'description' => 'Programme des Nations Unies pour le développement.', 'sort_order' => 10],
            ['name' => 'IDNorm', 'slug' => 'idnorm', 'logo_url' => '/images/partners/idnorm-logo.png', 'website' => 'https://idnorm.com', 'type' => 'tech', 'description' => 'Partenaire eKYC et vérification d\'identité numérique.', 'sort_order' => 11],
            ['name' => 'Advans Group', 'slug' => 'advans', 'logo_url' => '/images/partners/advans-logo.png', 'website' => 'https://www.advansgroup.com', 'type' => 'financial', 'description' => 'Institution de microfinance présente en Afrique et en Asie.', 'sort_order' => 12],
        ];

        foreach ($partners as $p) {
            Partner::updateOrCreate(['slug' => $p['slug']], $p);
        }

        // ─── Témoignages ────────────────────────────────────
        $testimonials = [
            [
                'author_name' => 'Aminata Diop',
                'author_role' => 'Fondatrice AgriDrone Sahel, Sénégal',
                'author_country' => 'Sénégal',
                'content' => 'Grâce à Globalafrica+, j\'ai pu formaliser mon entreprise, lever des fonds auprès de la diaspora et trouver un mentor expert en agritech. En 6 mois, mon chiffre d\'affaires a triplé. La plateforme a changé ma vie d\'entrepreneuse.',
                'rating' => 5,
                'project_title' => 'AgriDrone Sahel',
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'author_name' => 'Ibrahim Sow',
                'author_role' => 'Investisseur diaspora, Paris',
                'author_country' => 'France',
                'content' => 'Depuis Paris, je cherchais un moyen sûr d\'investir au Sénégal. Le système d\'escrow et la vérification KYC de Globalafrica+ m\'ont rassuré. J\'ai déjà investi dans 3 projets et les retours sont prometteurs. Le matching IA est vraiment pertinent.',
                'rating' => 5,
                'project_title' => null,
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'author_name' => 'Fatou Ndiaye',
                'author_role' => 'Mentor en stratégie digitale, Abidjan',
                'author_country' => 'Côte d\'Ivoire',
                'content' => 'Le module de mentorat est exceptionnel. J\'accompagne 5 entrepreneurs africains et je vois leur progression chaque semaine. Les outils de suivi de sessions et l\'évaluation structurée rendent le mentorat vraiment efficace.',
                'rating' => 5,
                'is_featured' => true,
                'sort_order' => 3,
            ],
            [
                'author_name' => 'Ousmane Ba',
                'author_role' => 'Développeur web, Dakar',
                'author_country' => 'Sénégal',
                'content' => 'En tant que chercheur d\'emploi, Globalafrica+ m\'a permis de postuler à des projets innovants directement. Le système de compétences et le matching m\'ont connecté avec AgriDrone Sahel où je suis maintenant lead développeur.',
                'rating' => 5,
                'project_title' => 'AgriDrone Sahel',
                'is_featured' => false,
                'sort_order' => 4,
            ],
            [
                'author_name' => 'Dr. Koné Mamadou',
                'author_role' => 'Directeur CEPICI, Côte d\'Ivoire',
                'author_country' => 'Côte d\'Ivoire',
                'content' => 'Globalafrica+ est un outil stratégique pour notre politique d\'attraction des investissements. Le portail gouvernement nous permet de publier nos appels à projets et de toucher la diaspora ivoirienne dans le monde entier.',
                'rating' => 5,
                'is_featured' => false,
                'sort_order' => 5,
            ],
            [
                'author_name' => 'Aïcha Traoré',
                'author_role' => 'Entrepreneuse commerce, Bamako',
                'author_country' => 'Mali',
                'content' => 'Le hub de formalisation m\'a guidée pas à pas pour enregistrer mon entreprise au Mali. Les templates de business plan gratuits et l\'accès aux partenaires de microfinance ont été décisifs pour obtenir mon premier prêt.',
                'rating' => 4,
                'is_featured' => false,
                'sort_order' => 6,
            ],
            [
                'author_name' => 'Jean-Baptiste Hakizimana',
                'author_role' => 'Ingénieur solaire, Kigali',
                'author_country' => 'Rwanda',
                'content' => 'La plateforme connecte vraiment l\'Afrique. Depuis Kigali, j\'ai trouvé un investisseur basé à Bruxelles et un partenaire technique au Sénégal pour mon projet de kits solaires. L\'Afrique unie, c\'est possible grâce au numérique.',
                'rating' => 5,
                'is_featured' => false,
                'sort_order' => 7,
            ],
        ];

        foreach ($testimonials as $t) {
            Testimonial::updateOrCreate(
                ['author_name' => $t['author_name'], 'content' => $t['content']],
                $t
            );
        }
    }
}
