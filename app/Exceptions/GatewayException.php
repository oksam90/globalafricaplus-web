<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Échec d'un prestataire externe : PSP de paiement, signature électronique,
 * vérification d'identité.
 *
 * Distincte d'une RuntimeException ordinaire, qui porte dans ce projet une
 * RÈGLE MÉTIER dont le message est écrit pour l'utilisateur et lui est renvoyé
 * tel quel.
 *
 * Le message d'un prestataire, lui, est écrit pour un développeur. Il décrit
 * l'état de NOTRE configuration, pas une action que l'utilisateur pourrait
 * corriger : « The API token in the request is invalid », « PAWAPAY_API_TOKEN
 * manquant », « HTTP 502 ». Le renvoyer au navigateur — ce que faisait le motif
 * `catch (\Throwable)` suivi de `getMessage()` — informe un attaquant sur nos
 * intégrations et n'aide personne d'autre.
 *
 * Controller::failure() la traduit donc en 502 avec un message neutre, et la
 * consigne en entier dans les journaux, là où elle sert.
 */
class GatewayException extends RuntimeException
{
    public static function for(string $gateway, string $detail): self
    {
        return new self(sprintf('%s : %s', $gateway, $detail));
    }
}
