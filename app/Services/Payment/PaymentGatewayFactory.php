<?php

namespace App\Services\Payment;

use App\Services\Payment\Gateways\PawaPayGateway;
use App\Services\Payment\Gateways\PayDunyaGateway;
use InvalidArgumentException;

/**
 * Strategy-Pattern factory qui sélectionne le PSP à utiliser.
 *
 * Depuis 2026-09 le PSP par défaut est **PawaPay** (config/payments.php).
 * PayDunya reste entièrement fonctionnel et réactivable en basculant
 * PAYMENT_GATEWAY=paydunya dans le .env — aucune configuration n'a été
 * supprimée.
 *
 * Routage :
 *   - pays couvert par PawaPay (config/pawapay.php `markets`) → PawaPay
 *   - sinon → `payments.fallback_gateway` (PayDunya), ou le PSP par défaut
 */
class PaymentGatewayFactory
{
    /**
     * Instancie un PSP par son identifiant.
     */
    public function make(string $gateway): PaymentGatewayInterface
    {
        return match (strtolower($gateway)) {
            'pawapay'  => app(PawaPayGateway::class),
            'paydunya' => app(PayDunyaGateway::class),
            default    => throw new InvalidArgumentException("Unsupported payment gateway: {$gateway}"),
        };
    }

    /** PSP par défaut de la plateforme. */
    public function default(): PaymentGatewayInterface
    {
        return $this->make((string) config('payments.default_gateway', 'pawapay'));
    }

    /**
     * PSP correspondant au moyen de paiement choisi par l'utilisateur :
     *   mobile_money → PawaPay, card → PayDunya.
     */
    public function forMethod(?string $method): PaymentGatewayInterface
    {
        $gateway = config('payments.methods.' . strtolower((string) $method) . '.gateway');

        return $this->make((string) ($gateway ?: config('payments.default_gateway', 'pawapay')));
    }

    /**
     * Sélection automatique à partir du code pays ISO-3166 alpha-2.
     */
    public function forCountry(string $countryCode): PaymentGatewayInterface
    {
        $countryCode = strtoupper($countryCode);
        $default     = (string) config('payments.default_gateway', 'pawapay');

        if ($default === 'pawapay' && $this->isPawaPayMarket($countryCode)) {
            return $this->make('pawapay');
        }

        if ($default !== 'pawapay') {
            return $this->make($default);
        }

        $fallback = config('payments.fallback_gateway');

        return $fallback ? $this->make((string) $fallback) : $this->make('pawapay');
    }

    /**
     * Sélection automatique à partir de la devise.
     */
    public function forCurrency(string $currency): PaymentGatewayInterface
    {
        $currency = strtoupper($currency);

        foreach (config('pawapay.markets', []) as $market) {
            if (($market['currency'] ?? null) === $currency) {
                return $this->default();
            }
        }

        return $this->default();
    }

    /** Le pays est-il un marché mobile money couvert par PawaPay ? */
    public function isPawaPayMarket(string $countryCode): bool
    {
        return isset(config('pawapay.markets', [])[strtoupper($countryCode)]);
    }

    public function isUEMOA(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), config('paydunya.uemoa_countries', []), true);
    }

    public function isCEMAC(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), config('paydunya.cemac_countries', []), true);
    }
}
