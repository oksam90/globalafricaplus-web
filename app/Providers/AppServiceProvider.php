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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
    }
}
