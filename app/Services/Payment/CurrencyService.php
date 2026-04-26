<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sprint 5 — Multi-currency conversion service.
 *
 * Source-of-truth rules:
 *   - EUR ↔ XOF / XAF use the BCEAO/BEAC peg: 1 EUR = 655.957 CFA (fixed by treaty).
 *   - All other pairs are fetched from a free public FX API (frankfurter.app),
 *     cached for 6 hours per pair, with a fallback table baked in for resilience.
 */
class CurrencyService
{
    private const CACHE_TTL = 21_600; // 6 hours
    private const PEG_EUR_XOF = 655.957;

    /**
     * Hard-coded fallback rates (against EUR). Used if the live API is down so
     * the platform never blocks a payment on transient FX issues. Roughly the
     * spot rate as of writing — accurate enough for invoice-level conversion
     * but the live API is preferred when available.
     */
    private const FALLBACK = [
        'EUR' => 1.0,
        'USD' => 1.08,
        'XOF' => self::PEG_EUR_XOF,
        'XAF' => self::PEG_EUR_XOF,
        'GBP' => 0.85,
        'NGN' => 1700.0,
        'GHS' => 16.0,
        'KES' => 140.0,
    ];

    public function getRate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        // CFA peg shortcut — never hit the network for these.
        if ($this->isPegged($from, $to)) {
            return $this->pegRate($from, $to);
        }

        $cacheKey = "fx.{$from}.{$to}";
        return (float) Cache::remember($cacheKey, self::CACHE_TTL, function () use ($from, $to) {
            try {
                $rate = $this->fetchLive($from, $to);
                if ($rate > 0) return $rate;
            } catch (\Throwable $e) {
                Log::warning('CurrencyService: live FX fetch failed', [
                    'from'    => $from,
                    'to'      => $to,
                    'message' => $e->getMessage(),
                ]);
            }

            return $this->fallbackRate($from, $to);
        });
    }

    public function convert(float $amount, string $from, string $to): float
    {
        return $amount * $this->getRate($from, $to);
    }

    /**
     * Round per-currency to a sane minor-unit precision.
     * XOF/XAF have no minor unit (round to nearest integer).
     */
    public function round(float $amount, string $currency): float
    {
        return in_array(strtoupper($currency), ['XOF', 'XAF', 'JPY'], true)
            ? (float) round($amount, 0)
            : (float) round($amount, 2);
    }

    protected function isPegged(string $from, string $to): bool
    {
        $cfa = ['XOF', 'XAF'];
        return ($from === 'EUR' && in_array($to, $cfa, true))
            || ($to === 'EUR' && in_array($from, $cfa, true))
            || (in_array($from, $cfa, true) && in_array($to, $cfa, true));
    }

    protected function pegRate(string $from, string $to): float
    {
        $cfa = ['XOF', 'XAF'];
        if (in_array($from, $cfa, true) && in_array($to, $cfa, true)) {
            return 1.0; // XOF↔XAF parity
        }
        if ($from === 'EUR') return self::PEG_EUR_XOF;
        return 1 / self::PEG_EUR_XOF;
    }

    protected function fetchLive(string $from, string $to): float
    {
        $resp = Http::timeout(5)
            ->retry(2, 200)
            ->get("https://api.frankfurter.app/latest", [
                'from' => $from,
                'to'   => $to,
            ]);

        if (!$resp->successful()) {
            throw new RuntimeException("Frankfurter HTTP {$resp->status()}");
        }

        $rate = (float) ($resp->json("rates.{$to}") ?? 0);
        if ($rate <= 0) {
            throw new RuntimeException('Empty rate in Frankfurter response');
        }
        return $rate;
    }

    protected function fallbackRate(string $from, string $to): float
    {
        $eurFrom = self::FALLBACK[$from] ?? null;
        $eurTo   = self::FALLBACK[$to]   ?? null;

        if ($eurFrom === null || $eurTo === null) {
            throw new RuntimeException("FX pair {$from}{$to} has no fallback rate.");
        }

        // FALLBACK is "X per 1 EUR", so converting from→to is (1/from) * to.
        return $eurTo / $eurFrom;
    }
}
