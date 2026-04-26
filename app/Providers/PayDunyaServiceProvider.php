<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Paydunya\Checkout\Store;
use Paydunya\Setup;

/**
 * Bootstraps the PayDunya PHP SDK with Globalafrica+ credentials.
 *
 * The SDK uses static singletons (Paydunya\Setup + Paydunya\Checkout\Store)
 * so configuring them once at boot is sufficient for every checkout request.
 */
class PayDunyaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (!class_exists(Setup::class)) {
            return; // SDK not installed — fail silent in non-payment environments.
        }

        $cfg = config('paydunya');

        if (empty($cfg['master_key'])) {
            return; // Not configured yet — skip boot.
        }

        Setup::setMasterKey($cfg['master_key']);
        Setup::setPublicKey($cfg['public_key']);
        Setup::setPrivateKey($cfg['private_key']);
        Setup::setToken($cfg['token']);
        Setup::setMode($cfg['mode'] ?? 'test');

        Store::setName($cfg['store']['name'] ?? 'Globalafrica+');
        Store::setTagline($cfg['store']['tagline'] ?? '');
        Store::setPhoneNumber($cfg['store']['phone'] ?? '');
        Store::setPostalAddress($cfg['store']['postal_address'] ?? '');
        Store::setWebsiteUrl($cfg['store']['website_url'] ?? '');
        Store::setLogoUrl($cfg['store']['logo_url'] ?? '');

        if (!empty($cfg['callback_url'])) {
            Store::setCallbackUrl($cfg['callback_url']);
        }
        if (!empty($cfg['return_url'])) {
            Store::setReturnUrl($cfg['return_url']);
        }
        if (!empty($cfg['cancel_url'])) {
            Store::setCancelUrl($cfg['cancel_url']);
        }
    }
}
