<?php

use App\Jobs\ExpireKYCVerification;
use App\Jobs\ProcessAutoRefund;
use App\Jobs\ProcessInstallmentDue;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Sprint 4 — daily sweep that refunds investors stuck in escrow > 90j.
        $schedule->job(new ProcessAutoRefund())
            ->dailyAt('03:15')
            ->name('escrow:auto-refund')
            ->withoutOverlapping()
            ->onOneServer();

        // Sprint 5 — daily sweep that invoices the next due installment for active plans.
        $schedule->job(new ProcessInstallmentDue())
            ->dailyAt('04:00')
            ->name('installments:process-due')
            ->withoutOverlapping()
            ->onOneServer();

        // Smile Identity Sprint 4 — daily sweep that expires 24-month-old KYC verifications.
        $schedule->job(new ExpireKYCVerification())
            ->dailyAt('02:30')
            ->name('kyc:expire-verifications')
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Treat empty form strings as null so `nullable` rules work as expected
        $middleware->convertEmptyStringsToNull();
        $middleware->trimStrings();

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'subscribed' => \App\Http\Middleware\CheckSubscription::class,
            'kyc' => \App\Http\Middleware\CheckKyc::class,
            'kyc.smile' => \App\Http\Middleware\RequireKYCLevel::class,
            'paydunya.webhook' => \App\Http\Middleware\VerifyPayDunyaWebhook::class,
            'smile.webhook' => \App\Http\Middleware\VerifySmileSignature::class,
        ]);

        // Webhook endpoints are server-to-server (no CSRF token).
        $middleware->validateCsrfTokens(except: [
            'api/v1/webhooks/paydunya',
            'api/webhooks/paydunya',
            'api/v1/webhooks/smile-identity',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
