<?php

namespace Database\Seeders;

use App\Models\KycSession;
use App\Models\User;
use Illuminate\Database\Seeder;

class KycSeeder extends Seeder
{
    public function run(): void
    {
        // Aminata (entrepreneur) — verified KYC
        $aminata = User::where('email', 'aminata@africaplus.test')->first();
        if ($aminata) {
            KycSession::updateOrCreate(
                ['user_id' => $aminata->id, 'status' => 'verified'],
                [
                    'provider' => 'idnorm',
                    'provider_session_id' => 'idnorm_dev_aminata_' . md5('aminata'),
                    'current_step' => 4,
                    'person_type' => 'physical',
                    'first_name' => 'Aminata',
                    'last_name' => 'Diop',
                    'date_of_birth' => '1992-03-15',
                    'place_of_birth' => 'Thies',
                    'nationality' => 'Senegalaise',
                    'gender' => 'female',
                    'address' => '12 Rue Abdou Diouf',
                    'city' => 'Thies',
                    'postal_code' => '26000',
                    'country' => 'Senegal',
                    'phone' => '+221771234567',
                    'document_type' => 'cni',
                    'document_number' => 'SN-1234567890',
                    'document_expiry' => '2028-12-31',
                    'document_issuing_country' => 'Senegal',
                    'document_front_url' => 'kyc/demo/cni_front.jpg',
                    'selfie_url' => 'kyc/demo/selfie.jpg',
                    'source_of_funds' => 'activite_commerciale',
                    'occupation' => 'Entrepreneuse agritech',
                    'expected_monthly_volume' => 15000,
                    'is_pep' => false,
                    'risk_level' => 'low',
                    'risk_factors' => [],
                    'aml_checked' => true,
                    'aml_clear' => true,
                    'sanctions_clear' => true,
                    'pep_clear' => true,
                    'aml_results' => [
                        'mode' => 'dev_simulation',
                        'sanctions_matches' => 0,
                        'pep_matches' => 0,
                        'adverse_media' => 0,
                    ],
                    'verified_at' => now()->subDays(15),
                    'provider_data' => ['mode' => 'dev_auto_verify'],
                ]
            );
            $aminata->update(['kyc_level' => 'verified']);
        }

        // Ibrahim (investor diaspora) — verified KYC
        $ibrahim = User::where('email', 'ibrahim@africaplus.test')->first();
        if ($ibrahim) {
            KycSession::updateOrCreate(
                ['user_id' => $ibrahim->id, 'status' => 'verified'],
                [
                    'provider' => 'idnorm',
                    'provider_session_id' => 'idnorm_dev_ibrahim_' . md5('ibrahim'),
                    'current_step' => 4,
                    'person_type' => 'physical',
                    'first_name' => 'Ibrahim',
                    'last_name' => 'Sow',
                    'date_of_birth' => '1985-07-20',
                    'place_of_birth' => 'Dakar',
                    'nationality' => 'Senegalaise',
                    'gender' => 'male',
                    'address' => '42 Avenue des Champs-Elysees',
                    'city' => 'Paris',
                    'postal_code' => '75008',
                    'country' => 'France',
                    'phone' => '+33612345678',
                    'document_type' => 'passport',
                    'document_number' => 'FR-9876543210',
                    'document_expiry' => '2029-06-30',
                    'document_issuing_country' => 'France',
                    'document_front_url' => 'kyc/demo/passport_front.jpg',
                    'selfie_url' => 'kyc/demo/selfie_ibrahim.jpg',
                    'proof_of_address_url' => 'kyc/demo/proof_address.pdf',
                    'source_of_funds' => 'investissements',
                    'occupation' => 'Investisseur',
                    'expected_monthly_volume' => 80000,
                    'is_pep' => false,
                    'risk_level' => 'medium',
                    'risk_factors' => ['Volume mensuel eleve (> 50 000 EUR)'],
                    'aml_checked' => true,
                    'aml_clear' => true,
                    'sanctions_clear' => true,
                    'pep_clear' => true,
                    'verified_at' => now()->subDays(10),
                    'provider_data' => ['mode' => 'dev_auto_verify'],
                ]
            );
            $ibrahim->update(['kyc_level' => 'verified']);
        }

        // Fatou (mentor) — KYC in progress (step 2)
        $fatou = User::where('email', 'fatou@africaplus.test')->first();
        if ($fatou) {
            KycSession::updateOrCreate(
                ['user_id' => $fatou->id, 'status' => 'in_progress'],
                [
                    'provider' => 'idnorm',
                    'current_step' => 2,
                    'person_type' => 'physical',
                    'first_name' => 'Fatou',
                    'last_name' => 'Ndiaye',
                    'date_of_birth' => '1988-11-05',
                    'place_of_birth' => 'Abidjan',
                    'nationality' => 'Ivoirienne',
                    'gender' => 'female',
                    'address' => '15 Boulevard Latrille',
                    'city' => 'Abidjan',
                    'country' => "Cote d'Ivoire",
                    'phone' => '+22507654321',
                    'source_of_funds' => null,
                    'occupation' => null,
                ]
            );
            // Fatou keeps kyc_level = 'none' (in progress)
        }

        // Admin — certified (bypasses KYC)
        $admin = User::where('email', 'admin@africaplus.test')->first();
        if ($admin) {
            $admin->update(['kyc_level' => 'certified']);
        }
    }
}
