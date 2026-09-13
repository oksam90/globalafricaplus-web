<?php

namespace App\Services\Convention\Signature;

use App\Models\Investment;

/**
 * Contrat commun aux prestataires de signature électronique.
 *
 * Implémentations : DocuSealProvider (auto-hébergé, actif), YousignProvider
 * (conservé et réactivable via SIGNATURE_PROVIDER=yousign).
 *
 * Le fournisseur utilisé est mémorisé sur chaque investissement
 * (`signature_provider`) : une convention envoyée chez l'un continue d'être
 * suivie chez lui même après bascule du prestataire par défaut.
 */
interface SignatureProviderInterface
{
    /** Identifiant court du prestataire : "docuseal", "yousign". */
    public function name(): string;

    /** Le prestataire dispose-t-il de ses identifiants dans cet environnement ? */
    public function isConfigured(): bool;

    /**
     * Le prestataire a-t-il besoin du PDF de la convention pour créer la
     * demande ?
     *
     * Faux en mode « gabarit » DocuSeal : le document vit chez DocuSeal, seules
     * les valeurs transitent. L'envoi ne doit donc pas être bloqué quand
     * LibreOffice est absent du serveur — le PDF n'est alors qu'une copie
     * d'archive, et la version signée est de toute façon récupérée ensuite.
     */
    public function requiresDocument(): bool;

    /**
     * Envoie la convention à la signature des deux parties.
     *
     * @param  array{investor:array{name:string,email:string},owner:array{name:string,email:string}}  $signers
     * @return array{request_id:string, sign_urls?:array<string,string>, raw?:array}
     */
    public function send(Investment $investment, string $pdfBinary, string $documentName, array $signers): array;

    /**
     * Statut NORMALISÉ de la demande :
     * "pending" | "completed" | "declined" | "expired" | "unknown".
     * Le statut brut du prestataire est renvoyé dans `raw`.
     *
     * @return array{status:string, raw:string}
     */
    public function status(Investment $investment): array;

    /**
     * Contenu binaire du PDF signé, ou null s'il n'est pas encore disponible.
     */
    public function downloadSigned(Investment $investment): ?string;
}
