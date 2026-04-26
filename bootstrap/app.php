<?php

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
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Treat empty form strings as null so `nullable` rules work as expected
        $middleware->convertEmptyStringsToNull();
        $middleware->trimStrings();

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'subscribed' => \App\Http\Middleware\CheckSubscription::class,
            'kyc' => \App\Http\Middleware\CheckKyc::class,
            'paydunya.webhook' => \App\Http\Middleware\VerifyPayDunyaWebhook::class,
        ]);

        // PayDunya webhook endpoint is server-to-server (no CSRF token).
        $middleware->validateCsrfTokens(except: [
            'api/v1/webhooks/paydunya',
            'api/webhooks/paydunya',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
