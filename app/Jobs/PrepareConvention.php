<?php

namespace App\Jobs;

use App\Models\Investment;
use App\Services\Convention\ConventionGenerator;
use App\Services\Convention\ConventionSignatureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Produit la convention d'un investissement, et l'envoie éventuellement à la
 * signature.
 *
 * Les deux actes sont dissociés, parce qu'ils n'engagent pas les mêmes choses :
 *
 *   • PRODUIRE n'a aucun effet vers l'extérieur — c'est un fichier. On le fait
 *     dès la validation, pour que l'investisseur dispose de son contrat au
 *     moment où il s'engage.
 *
 *   • ENVOYER sollicite un TIERS : le porteur de projet reçoit une demande de
 *     signature. On attend donc l'encaissement, sans quoi un abandon au
 *     checkout — ordinaire en mobile money — laisserait une convention
 *     orpheline à signer pour de l'argent jamais reçu.
 *
 * Hors du cycle de requête : la conversion .docx → PDF passe par LibreOffice
 * (plusieurs secondes) et l'envoi appelle l'API du prestataire. En synchrone,
 * l'investisseur attendrait tout cela avant d'être redirigé vers sa page de
 * paiement.
 *
 * Idempotent de bout en bout : le générateur rend le chemin existant si la
 * convention est déjà produite, et `sendForSignature()` sort immédiatement si
 * une demande porte déjà un identifiant.
 */
class PrepareConvention implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        public int $investmentId,
        /** Mettre aussi à la signature, ou se contenter de produire le document. */
        public bool $send = false,
    ) {}

    public function handle(ConventionGenerator $generator, ConventionSignatureService $signatures): void
    {
        $investment = Investment::find($this->investmentId);

        if (!$investment) {
            Log::warning('convention.job_investment_missing', ['investment_id' => $this->investmentId]);

            return;
        }

        if ($investment->signature_request_id) {
            return; // déjà à la signature : plus rien à faire
        }

        // 1) Le document. Doit être produit APRÈS l'éventuel plan d'échéances :
        //    sans lui, la convention décrirait un versement unique alors que
        //    l'investisseur a choisi d'étaler son paiement.
        try {
            $generator->generateForInvestment($investment);
            $investment->refresh();
        } catch (\Throwable $e) {
            Log::warning('convention.generate_failed', [
                'investment_id' => $investment->id,
                'type'          => $investment->type,
                'message'       => $e->getMessage(),
            ]);

            return;
        }

        if (!$this->send || !config('signature.auto_send')) {
            return;
        }

        // 2) La mise à la signature.
        try {
            $signatures->sendForSignature($investment);
        } catch (\Throwable $e) {
            Log::warning('convention.signature_send_failed', [
                'investment_id' => $investment->id,
                'message'       => $e->getMessage(),
            ]);
        }
    }
}
