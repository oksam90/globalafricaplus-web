<?php

namespace App\Services\Convention;

use App\Models\Investment;
use App\Services\Convention\Signature\DocuSealProvider;
use App\Services\Convention\Signature\SignatureProviderInterface;
use App\Services\Convention\Signature\YousignProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Orchestre la signature électronique des conventions.
 *
 *   sendForSignature() : PDF → prestataire (création de la demande + envoi)
 *   syncStatus()       : interroge le prestataire et récupère le PDF signé.
 *
 * Le prestataire actif est piloté par `signature.provider` (DocuSeal
 * auto-hébergé par défaut, Yousign conservé et réactivable). Le fournisseur
 * réellement utilisé est mémorisé sur l'investissement : une convention
 * envoyée chez Yousign avant la bascule reste suivie chez Yousign.
 */
class ConventionSignatureService
{
    public function __construct(
        private readonly ConventionGenerator $generator = new ConventionGenerator(),
    ) {}

    /**
     * Envoie la convention d'un investissement à la signature des deux parties.
     * Idempotent : si une demande existe déjà, on ne recrée pas.
     */
    public function sendForSignature(Investment $investment): Investment
    {
        if ($investment->signature_request_id) {
            return $investment; // déjà envoyé
        }

        $provider = $this->provider();
        if (!$provider->isConfigured()) {
            throw new RuntimeException(
                'Signature indisponible : ' . $provider->name() . ' n\'est pas configuré.'
            );
        }

        // S'assurer que la convention est produite (génère le .docx et, si
        // LibreOffice est disponible, sa version PDF).
        if (!$investment->contract_path) {
            $this->generator->generateForInvestment($investment);
            $investment->refresh();
        }

        $disk    = (string) config('conventions.disk', 'local');
        $hasPdf  = $investment->contract_pdf_path
            && Storage::disk($disk)->exists($investment->contract_pdf_path);

        // Le PDF n'est exigé que par les prestataires qui téléversent le
        // document. En mode « gabarit » DocuSeal, il n'est qu'une copie
        // d'archive : son absence (LibreOffice non installé sur le serveur) ne
        // doit pas empêcher la mise à la signature.
        if (!$hasPdf) {
            if ($provider->requiresDocument()) {
                throw new RuntimeException('PDF de la convention introuvable (conversion LibreOffice ?).');
            }

            Log::warning('convention.pdf_missing_but_sent', [
                'investment_id' => $investment->id,
                'provider'      => $provider->name(),
            ]);
        }

        $investment->loadMissing(['investor', 'project.user']);
        $investor = $investment->investor;
        $owner    = $investment->project?->user;
        if (!$investor?->email || !$owner?->email) {
            throw new RuntimeException('Email manquant pour un des signataires.');
        }

        $pdfBinary = $hasPdf ? Storage::disk($disk)->get($investment->contract_pdf_path) : '';
        $name = 'Convention ' . ($investment->contract_type ?: $investment->type)
            . ' — investissement #' . $investment->id;

        $result = $provider->send($investment, $pdfBinary, $name, [
            'investor' => ['name' => (string) $investor->name, 'email' => (string) $investor->email],
            'owner'    => ['name' => (string) $owner->name,    'email' => (string) $owner->email],
        ]);

        $investment->forceFill([
            'signature_provider'   => $provider->name(),
            'signature_request_id' => $result['request_id'],
            'signature_sign_urls'  => $result['sign_urls'] ?? null,
            'contract_status'      => 'sent',
            'contract_sent_at'     => now(),
        ])->save();

        Log::info('convention.signature_sent', [
            'investment_id' => $investment->id,
            'provider'      => $provider->name(),
            'request_id'    => $result['request_id'],
        ]);

        return $investment;
    }

    /**
     * Interroge le prestataire et, si la signature est terminée, récupère le
     * PDF signé. Retourne le statut brut du prestataire.
     */
    public function syncStatus(Investment $investment): string
    {
        if (!$investment->signature_request_id) {
            return 'none';
        }

        // On suit la demande chez le prestataire qui l'a créée, pas chez le
        // prestataire actif : indispensable pendant la période de bascule.
        $provider = $this->provider($investment->signature_provider);
        $state    = $provider->status($investment);

        if ($state['status'] === 'completed' && $investment->contract_status !== 'signed') {
            $signed = $provider->downloadSigned($investment);

            if ($signed !== null && $signed !== '') {
                $disk = (string) config('conventions.disk', 'local');
                $signedPath = sprintf(
                    '%s/%d/Convention_%s_%d_signe.pdf',
                    trim((string) config('conventions.storage_dir', 'contracts'), '/'),
                    $investment->id,
                    $investment->contract_type ?: $investment->type,
                    $investment->id,
                );
                Storage::disk($disk)->put($signedPath, $signed);

                $investment->forceFill([
                    'contract_signed_path' => $signedPath,
                    'contract_status'      => 'signed',
                    'contract_signed_at'   => now(),
                ])->save();

                Log::info('convention.signed', [
                    'investment_id' => $investment->id,
                    'provider'      => $provider->name(),
                ]);
            }
        } elseif (in_array($state['status'], ['declined', 'expired'], true)) {
            $investment->forceFill(['contract_status' => 'failed'])->save();
        }

        return $state['raw'];
    }

    /**
     * Résout un prestataire par son nom, ou le prestataire actif par défaut.
     */
    public function provider(?string $name = null): SignatureProviderInterface
    {
        $name = strtolower((string) ($name ?: config('signature.provider', 'docuseal')));

        return match ($name) {
            'docuseal' => new DocuSealProvider(),
            'yousign'  => new YousignProvider(),
            default    => throw new InvalidArgumentException("Prestataire de signature inconnu : {$name}"),
        };
    }
}
