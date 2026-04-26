<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 5 — Endpoint de consultation des taux de change.
 *
 *   GET /api/exchange-rates/{from}/{to}
 *   GET /api/exchange-rates/xof-eur   (alias spec §6.1)
 */
class ExchangeRateController extends Controller
{
    public function __construct(
        protected CurrencyService $currency,
    ) {}

    public function show(Request $request, string $from, string $to): JsonResponse
    {
        try {
            $rate = $this->currency->getRate($from, $to);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'from' => strtoupper($from),
            'to'   => strtoupper($to),
            'rate' => $rate,
            'inverse' => $rate > 0 ? 1 / $rate : null,
            'fetched_at' => now()->toIso8601String(),
        ]);
    }

    public function xofEur(): JsonResponse
    {
        return $this->show(request(), 'XOF', 'EUR');
    }
}
