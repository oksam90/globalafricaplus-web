<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\FeeCalculator;
use Illuminate\Http\JsonResponse;

/**
 * Expose les moyens de paiement activés (mobile money PawaPay / carte bancaire
 * PayDunya) ainsi que le barème PUBLIC de commission.
 *
 * Les montants pivots du barème ne sont volontairement jamais renvoyés.
 */
class PaymentMethodController extends Controller
{
    public function index(FeeCalculator $fees): JsonResponse
    {
        return response()->json([
            'methods'        => array_values($fees->availableMethods()),
            'default_method' => $fees->defaultMethod(),
            'commission'     => $fees->publicCommissionScale(),
        ]);
    }

    /**
     * Opérateurs mobile money disponibles pour un pays (sélection du compte de
     * réception côté porteur de projet).
     */
    public function providers(string $country, FeeCalculator $fees): JsonResponse
    {
        $iso = $fees->normalizeCountry($country);

        if (!$fees->hasPawaPayMarket($iso)) {
            return response()->json([
                'country'   => $iso,
                'supported' => false,
                'providers' => [],
                'message'   => 'Le décaissement mobile money n\'est pas disponible dans ce pays — utilisez le virement bancaire (IBAN).',
            ]);
        }

        $market = $fees->market($iso);

        $providers = [];
        foreach ($market['providers'] ?? [] as $code => $conf) {
            $providers[] = [
                'code'  => $code,
                'label' => $conf['label'] ?? $code,
            ];
        }

        return response()->json([
            'country'   => $iso,
            'name'      => $market['name'] ?? $iso,
            'currency'  => $market['currency'] ?? null,
            'prefix'    => $market['prefix'] ?? null,
            'supported' => true,
            'providers' => $providers,
        ]);
    }
}
