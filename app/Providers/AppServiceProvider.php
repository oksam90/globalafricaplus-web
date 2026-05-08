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

        RateLimiter::for('admin-write', static fn (Request $request) =>
            Limit::perHour(60)->by($byUserOrIp($request))
        );

        RateLimiter::for('admin-read', static fn (Request $request) =>
            Limit::perMinute(120)->by($byUserOrIp($request))
        );
    }
}
