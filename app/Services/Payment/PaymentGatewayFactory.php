<?php

namespace App\Services\Payment;

use App\Services\Payment\Gateways\PayDunyaGateway;
use InvalidArgumentException;

/**
 * Strategy-Pattern factory that selects the appropriate payment gateway
 * based on the payer's country code or an explicit gateway name.
 *
 * Routing rules (per Globalafrica+ PayDunya spec §7):
 *   - UEMOA countries (SN, CI, ML, BF, TG, BJ, NE, GW) → PayDunya
 *   - CEMAC countries (CM, CF, TD, CG, GQ, GA)         → PayDunya (XAF)
 *   - Nigeria, Ghana, Kenya (NG, GH, KE)               → Flutterwave (TODO)
 *   - Rest of world                                     → Stripe (TODO)
 */
class PaymentGatewayFactory
{
    /**
     * Instantiate a gateway by its string identifier.
     */
    public function make(string $gateway): PaymentGatewayInterface
    {
        return match (strtolower($gateway)) {
            'paydunya'    => app(PayDunyaGateway::class),
            // 'flutterwave' => app(FlutterwaveGateway::class),
            // 'stripe'      => app(StripeGateway::class),
            // 'paypal'      => app(PayPalGateway::class),
            default       => throw new InvalidArgumentException("Unsupported payment gateway: {$gateway}"),
        };
    }

    /**
     * Choose a gateway automatically based on the ISO-3166 alpha-2 country code.
     */
    public function forCountry(string $countryCode): PaymentGatewayInterface
    {
        $countryCode = strtoupper($countryCode);

        if ($this->isUEMOA($countryCode) || $this->isCEMAC($countryCode)) {
            return $this->make('paydunya');
        }

        if (in_array($countryCode, ['NG', 'GH', 'KE'], true)) {
            // Fallback to PayDunya until Flutterwave adapter is implemented.
            return $this->make('paydunya');
        }

        // Default: Stripe (not yet implemented) → fallback PayDunya for dev.
        return $this->make('paydunya');
    }

    /**
     * Choose a gateway automatically based on the currency.
     */
    public function forCurrency(string $currency): PaymentGatewayInterface
    {
        return match (strtoupper($currency)) {
            'XOF', 'XAF' => $this->make('paydunya'),
            default       => $this->make('paydunya'), // TODO: Stripe for EUR/USD
        };
    }

    public function isUEMOA(string $countryCode): bool
    {
        return in_array(
            strtoupper($countryCode),
            config('paydunya.uemoa_countries', []),
            true,
        );
    }

    public function isCEMAC(string $countryCode): bool
    {
        return in_array(
            strtoupper($countryCode),
            config('paydunya.cemac_countries', []),
            true,
        );
    }
}
