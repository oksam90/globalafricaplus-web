<?php

namespace App\Providers;

use App\Events\AMLFlagged;
use App\Events\KYCRejected;
use App\Events\KYCVerified;
use App\Listeners\ReportSuspiciousActivity;
use App\Listeners\SendKYCNotification;
use App\Listeners\UnlockKYCFeatures;
use App\Models\Project;
use App\Policies\ProjectPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);

        // Sprint 4 — Smile Identity event wiring.
        // SendKYCNotification has multiple handler methods, so we map them
        // explicitly rather than relying on auto-discovery.
        Event::listen(KYCVerified::class, [SendKYCNotification::class, 'handleKYCVerified']);
        Event::listen(KYCRejected::class, [SendKYCNotification::class, 'handleKYCRejected']);
        Event::listen(AMLFlagged::class,  [SendKYCNotification::class, 'handleAMLFlagged']);

        // UnlockKYCFeatures + ReportSuspiciousActivity each have a single
        // handle($event) method, so auto-discovery would already work, but
        // registering them here keeps all KYC wiring in one place.
        Event::listen(KYCVerified::class, UnlockKYCFeatures::class);
        Event::listen(AMLFlagged::class,  ReportSuspiciousActivity::class);

        // ────────────────────────────────────────────────────────────────
        // Audit fix 2026-05 — rate-limiters for sensitive endpoints
        //
        // Rationale per bucket:
        //  - kyc-submissions  : every POST to /v1/kyc/{basic,biometric,document,
        //                       aml,web-token} hits the paid Smile Identity API
        //                       and writes a row in kyc_verifications. A user
        //                       has no legitimate reason to retry more than a
        //                       handful of times per hour.
        //  - kyc-reads        : status / history poll the local DB only —
        //                       generous limit so the SPA bell + KYC dashboard
        //                       stay snappy.
        //  - admin-write      : destructive mutations (delete user / mentor /
        //                       training, moderate project, toggle role).
        //                       Anti-runaway-script guard.
        //  - admin-read       : analytics / lists. Bigger window, mostly to
        //                       block credential-stuffing enumeration.
        //
        // Keys:
        //  - authenticated requests are bucketed by user id;
        //  - unauthenticated falls back to client IP.
        // ────────────────────────────────────────────────────────────────
        $byUserOrIp = static fn (Request $request): string => (string) ($request->user()?->id ?: $request->ip());

        RateLimiter::for('kyc-submissions', static fn (Request $request) =>
            Limit::perHour(10)->by($byUserOrIp($request))
        );

        RateLimiter::for('kyc-reads', static fn (Request $request) =>
            Limit::perMinute(60)->by($byUserOrIp($request))
        );

        // ────────────────────────────────────────────────────────────────
        //  AML screening — tighter than kyc-submissions because each call
        //  hits Smile's paid AML endpoint AND triggers a full DB write +
        //  PEP/sanctions match scan. Normal usage is one screening per
        //  investor profile, so 3/hour is plenty and breaks abuse loops.
        // ────────────────────────────────────────────────────────────────
        RateLimiter::for('kyc-aml', static fn (Request $request) =>
            Limit::perHour(3)->by($byUserOrIp($request))
        );

        RateLimiter::for('admin-write', static fn (Request $request) =>
            Limit::perHour(60)->by($byUserOrIp($request))
        );

        RateLimiter::for('admin-read', static fn (Request $request) =>
            Limit::perMinute(120)->by($byUserOrIp($request))
        );

        // ────────────────────────────────────────────────────────────────
        //  Admin uploads — image files cap at 4 MB each. 20/h per admin
        //  bounds disk consumption to ~80 MB/h per admin even if a script
        //  goes wild, vs. the 240 MB/h that admin-write (60/h) would allow.
        // ────────────────────────────────────────────────────────────────
        RateLimiter::for('admin-upload', static fn (Request $request) =>
            Limit::perHour(20)->by($byUserOrIp($request))
        );

        // ────────────────────────────────────────────────────────────────
        //  Webhooks (Smile, PayDunya) — defence in depth alongside the
        //  HMAC signature middleware. 200/min per source IP breaks a
        //  replay-flood that survives the signature check (e.g. leaked
        //  signing key) without rate-limiting legitimate burst traffic
        //  from the provider's IP pool.
        // ────────────────────────────────────────────────────────────────
        RateLimiter::for('webhooks', static fn (Request $request) =>
            Limit::perMinute(200)->by((string) $request->ip())
        );

        // Contact form on /tarifs (Enterprise "Nous contacter").
        // Bucketed by IP since the endpoint is public; a low-frequency
        // bucket is enough to break spam-bot loops without blocking
        // legitimate prospects.
        RateLimiter::for('contact-form', static fn (Request $request) =>
            Limit::perHour(5)->by((string) $request->ip())
        );
    }
}
