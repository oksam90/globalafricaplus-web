<?php

namespace App\Http\Controllers;

use App\Exceptions\GatewayException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Traduit une exception en réponse, selon ce qu'elle est vraiment.
     *
     * Le motif d'origine — `catch (\Throwable $e)` suivi de
     * `$e->getMessage()` en 422 — confondait trois choses de nature
     * différente :
     *
     *   • un REFUS D'AUTORISATION, qui doit répondre 403 sans rien révéler :
     *     un 422 accompagné de « Vous n'êtes pas l'investisseur de ce jalon »
     *     confirme au passage que la ressource existe ;
     *
     *   • une RÈGLE MÉTIER non satisfaite — « La fenêtre de garantie de 30
     *     jours est dépassée » — dont le message est écrit pour l'utilisateur
     *     et doit lui parvenir tel quel, en 422 ;
     *
     *   • une ERREUR INTERNE : erreur SQL, TypeError, panne réseau. Son
     *     message est écrit pour un développeur et contient volontiers un nom
     *     de table ou un chemin de fichier. Elle ne doit JAMAIS atteindre le
     *     navigateur — `APP_DEBUG=false` n'y change rien, puisque c'est le
     *     code applicatif qui la recopie.
     *
     * Les exceptions internes partent dans les journaux via `report()`, avec
     * leur trace complète, là où elles sont utiles.
     */
    protected function failure(
        Throwable $e,
        string $fallback = 'Une erreur est survenue. Réessayez dans un instant.',
    ): JsonResponse {
        if ($e instanceof AuthorizationException) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        // Panne d'un prestataire externe. Son message décrit l'état de NOTRE
        // configuration — « The API token in the request is invalid » — et n'a
        // rien à faire dans un navigateur. Vérifié : ce texte remontait bien
        // jusqu'au client avant ce correctif.
        if ($e instanceof GatewayException) {
            report($e);

            return response()->json([
                'message' => 'Le service de paiement est momentanément indisponible. Réessayez dans un instant.',
            ], 502);
        }

        // RuntimeException est, dans ce projet, le marqueur des règles métier :
        // les services l'utilisent pour des messages destinés à l'utilisateur.
        if ($e instanceof RuntimeException) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        report($e);

        return response()->json(['message' => $fallback], 500);
    }
}
