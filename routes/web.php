<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RoleProfileController;
use App\Http\Controllers\Api\SdgController;
use App\Http\Controllers\Api\SectorController;
use App\Http\Controllers\Api\DiasporaController;
use App\Http\Controllers\Api\MentoratController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GovernmentController;
use App\Http\Controllers\Api\FormalizationController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\EscrowController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\InstallmentController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\TrainingController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SmileKYCController;
use App\Http\Controllers\Api\SmileWebhookController;
use App\Http\Controllers\Api\AdvertisingController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// API routes (session-based auth via web guard)
Route::prefix('api')->group(function () {
    // Public
    Route::get('/stats', [StatsController::class, 'index']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/sdgs', [SdgController::class, 'index']);

    // Sectors (enriched categories)
    Route::get('/sectors', [SectorController::class, 'index']);
    Route::get('/sectors/{slug}', [SectorController::class, 'show']);

    // Diaspora portal (public)
    Route::get('/diaspora/stats', [DiasporaController::class, 'stats']);
    Route::get('/diaspora/countries', [DiasporaController::class, 'countries']);
    Route::get('/diaspora/countries/{code}', [DiasporaController::class, 'countryShow']);
    Route::post('/diaspora/simulate', [DiasporaController::class, 'simulate']);
    Route::get('/diaspora/projects', [DiasporaController::class, 'projects']);

    // Formalisation hub (public)
    Route::get('/formalisation/stats', [FormalizationController::class, 'stats']);
    Route::get('/formalisation/countries', [FormalizationController::class, 'countries']);
    Route::get('/formalisation/countries/{country}/steps', [FormalizationController::class, 'countrySteps']);
    Route::get('/formalisation/templates', [FormalizationController::class, 'templates']);
    Route::get('/formalisation/templates/{slug}', [FormalizationController::class, 'templateShow']);
    Route::get('/formalisation/partners', [FormalizationController::class, 'partners']);

    // Emploi portal (public)
    Route::get('/emploi/stats', [JobController::class, 'stats']);
    Route::get('/emploi/listings', [JobController::class, 'listings']);
    Route::get('/emploi/skills', [JobController::class, 'skillsList']);

    // Subscription plans (public)
    Route::get('/subscription/plans', [SubscriptionController::class, 'plans']);

    // Trainings catalog (public — Sprint 5)
    Route::get('/trainings', [TrainingController::class, 'index']);
    Route::get('/trainings/{slug}', [TrainingController::class, 'show']);

    // Exchange rates (public — Sprint 5)
    Route::get('/exchange-rates/xof-eur', [ExchangeRateController::class, 'xofEur']);
    Route::get('/exchange-rates/{from}/{to}', [ExchangeRateController::class, 'show'])
        ->whereAlpha('from')->whereAlpha('to');

    // Enterprise contact form (public, rate-limited)
    Route::post('/contact', [\App\Http\Controllers\Api\ContactController::class, 'send'])
        ->middleware('throttle:contact-form');

    // Smile Identity webhook (Sprint 2 — signature verified by middleware)
    Route::post('/v1/webhooks/smile-identity', [SmileWebhookController::class, 'handle'])
        ->middleware('smile.webhook');

    // PayDunya IPN webhook (public — signature verified by middleware)
    Route::post('/v1/webhooks/paydunya', [WebhookController::class, 'paydunya'])
        ->middleware('paydunya.webhook');
    // Alias without the /v1 prefix to match legacy docs
    Route::post('/webhooks/paydunya', [WebhookController::class, 'paydunya'])
        ->middleware('paydunya.webhook');

    // Advertising, Partners & Testimonials (public)
    Route::get('/advertising/banners', [AdvertisingController::class, 'banners']);
    Route::post('/advertising/banners/{id}/click', [AdvertisingController::class, 'bannerClick']);
    Route::get('/advertising/partners', [AdvertisingController::class, 'partners']);
    Route::get('/advertising/testimonials', [AdvertisingController::class, 'testimonials']);

    // Government portal (public)
    Route::get('/gouvernement/stats', [GovernmentController::class, 'stats']);
    Route::get('/gouvernement/appels', [GovernmentController::class, 'calls']);
    Route::get('/gouvernement/appels/{slug}', [GovernmentController::class, 'callShow']);
    Route::get('/gouvernement/zones', [GovernmentController::class, 'zones']);

    // Mentorat portal (public)
    Route::get('/mentorat/stats', [MentoratController::class, 'stats']);
    Route::get('/mentorat/skills', [MentoratController::class, 'skills']);
    Route::get('/mentorat/mentors', [MentoratController::class, 'mentors']);
    Route::get('/mentorat/mentors/{id}', [MentoratController::class, 'mentorShow']);

    // Projects (public)
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{slug}', [ProjectController::class, 'show']);

    // Auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Authenticated
    Route::middleware('auth')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Dashboard (role-contextual)
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Notifications (database channel — bell icon)
        Route::get('/notifications',                    [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count',       [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read',         [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all',          [NotificationController::class, 'markAllRead']);
        Route::delete('/notifications/{id}',            [NotificationController::class, 'destroy']);

        // Current user / multi-role management
        Route::get('/me', [MeController::class, 'show']);
        Route::patch('/me', [MeController::class, 'update']);
        Route::post('/me/roles', [MeController::class, 'attachRole']);
        Route::delete('/me/roles/{slug}', [MeController::class, 'detachRole']);
        Route::post('/me/active-role', [MeController::class, 'setActiveRole']);

        // Role-specific profile
        Route::get('/me/profiles/{roleSlug}', [RoleProfileController::class, 'show']);
        Route::put('/me/profiles/{roleSlug}', [RoleProfileController::class, 'update']);

        // Subscription management
        Route::get('/subscription/my', [SubscriptionController::class, 'mySubscription']);
        Route::post('/subscription/subscribe', [SubscriptionController::class, 'subscribe']);
        Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/subscription/refund', [SubscriptionController::class, 'refund']);
        Route::post('/subscription/verify', [SubscriptionController::class, 'verify']);

        // Trainings (Sprint 5)
        Route::get('/trainings/mine', [TrainingController::class, 'mine']);
        Route::post('/trainings/{slug}/purchase', [TrainingController::class, 'purchase']);
        Route::post('/trainings/verify', [TrainingController::class, 'verify']);
        Route::post('/trainings/purchases/{purchase}/refund', [TrainingController::class, 'refund']);

        // ─── Read-only finance endpoints (auth only) ────────────────────
        // Listing & verifying don't move money — KYC gating would only block
        // legitimate users from auditing their own state. The verify endpoint
        // is post-callback and idempotent.
        Route::get('/installments/mine',                         [InstallmentController::class, 'mine']);
        Route::get('/investments/mine',                          [InvestmentController::class, 'mine']);
        Route::get('/investments/{investment}',                  [InvestmentController::class, 'show']);
        Route::post('/investments/verify',                       [InvestmentController::class, 'verify']);
        Route::get('/escrow/milestones/mine',                    [EscrowController::class, 'mine']);
        Route::get('/escrow/projects/{project}/milestones',      [EscrowController::class, 'projectMilestones']);

        // ─── Investor actions — Pro/Entreprise + KYC + AML (Optimisation points 2 & 3) ─
        // Investing is gated to Pro / Entreprise tiers per the commercial
        // model. A validated investor profile (KYC verified + AML cleared)
        // is required by UEMOA Directive N° 02/2015/CM/UEMOA Art. 18 and
        // platform LCB-FT policy.
        Route::middleware([
            'subscribed:pro,enterprise',
            'kyc.smile:verified',
            'aml.checked',
        ])->group(function () {
            Route::post('/investments',                              [InvestmentController::class, 'store']);
            Route::post('/investments/{investment}/installments',    [InstallmentController::class, 'createForInvestment']);
            Route::post('/installments/{plan}/pay-next',             [InstallmentController::class, 'payNext']);
            Route::post('/escrow/milestones/{milestone}/reject',     [EscrowController::class, 'reject']);
        });

        // ─── Entrepreneur escrow action — any paid plan ──────────────────
        // Submitting milestone evidence is a project-owner action, kept on
        // the generic `subscribed` gate.
        Route::middleware(['subscribed', 'kyc.smile:verified'])->group(function () {
            Route::post('/escrow/milestones/{milestone}/submit',     [EscrowController::class, 'submit']);
        });

        // ─── Escrow funds release — `certified` tier + Pro/Entreprise + AML ─
        // Approving a milestone is an investor action that triggers a real
        // disbursement to the project owner. The spec (§ 8) calls for
        // `kyc:certified`; we pair it with the Pro/Entreprise gate and
        // require AML clearance per LCB-FT policy.
        Route::middleware([
            'subscribed:pro,enterprise',
            'kyc.smile:certified',
            'aml.checked',
        ])->group(function () {
            Route::post('/escrow/milestones/{milestone}/approve',    [EscrowController::class, 'approve']);
        });

        // ─── Smile Identity eKYC (Sprint 2 — legacy IDnorm endpoints removed in audit 2026-05) ─
        Route::prefix('v1/kyc')->name('kyc.')->group(function () {
            // Reads — generous bucket so the SPA bell + KYC dashboard stay snappy.
            Route::middleware('throttle:kyc-reads')->group(function () {
                Route::get('/status',    [SmileKYCController::class, 'status'])->name('status');
                Route::get('/history',   [SmileKYCController::class, 'history'])->name('history');
            });
            // Submissions — each call hits paid Smile API + DB row, hard cap.
            Route::middleware('throttle:kyc-submissions')->group(function () {
                Route::post('/basic',     [SmileKYCController::class, 'basic'])->name('basic');
                Route::post('/biometric', [SmileKYCController::class, 'biometric'])->name('biometric');
                Route::post('/document',  [SmileKYCController::class, 'document'])->name('document');
                Route::post('/aml',       [SmileKYCController::class, 'aml'])->name('aml');
                Route::post('/web-token', [SmileKYCController::class, 'webToken'])->name('web-token');
            });
        });

        // My projects (all statuses)
        Route::get('/me/projects', [ProjectController::class, 'mine']);

        // Follow / unfollow (any authenticated user)
        Route::post('/projects/{project}/follow', [ProjectController::class, 'follow']);
        Route::delete('/projects/{project}/follow', [ProjectController::class, 'unfollow']);

        // Project updates (news)
        Route::get('/projects/{project}/updates', [ProjectController::class, 'listUpdates']);

        // Mentorat (authenticated — read is free, actions require subscription)
        Route::get('/mentorat/my', [MentoratController::class, 'myMentorships']);
        Route::get('/mentorat/availabilities', [MentoratController::class, 'myAvailabilities']);
        Route::middleware('subscribed')->group(function () {
            Route::post('/mentorat/request', [MentoratController::class, 'requestMentorship']);
            Route::post('/mentorat/{mentorship}/respond', [MentoratController::class, 'respond']);
            Route::post('/mentorat/{mentorship}/complete', [MentoratController::class, 'complete']);
            Route::post('/mentorat/{mentorship}/sessions', [MentoratController::class, 'addSession']);
            Route::patch('/mentorat/sessions/{session}', [MentoratController::class, 'updateSession']);
            Route::post('/mentorat/{mentorship}/review', [MentoratController::class, 'review']);
            Route::put('/mentorat/availabilities', [MentoratController::class, 'saveAvailabilities']);
        });

        // Formalisation (authenticated — read is free, write requires subscription)
        Route::get('/formalisation/mon-parcours', [FormalizationController::class, 'myProgress']);
        Route::get('/formalisation/mon-resume', [FormalizationController::class, 'mySummary']);
        Route::middleware('subscribed')->group(function () {
            Route::post('/formalisation/steps/{stepId}/progress', [FormalizationController::class, 'updateProgress']);
        });

        // Emploi (authenticated — read is free, apply/sync requires subscription)
        Route::get('/emploi/mes-candidatures', [JobController::class, 'myApplications']);
        Route::get('/emploi/mes-competences', [JobController::class, 'mySkills']);
        Route::middleware('subscribed')->group(function () {
            Route::post('/emploi/projects/{projectId}/apply', [JobController::class, 'apply']);
            Route::delete('/emploi/candidatures/{id}', [JobController::class, 'withdraw']);
            Route::put('/emploi/mes-competences', [JobController::class, 'syncSkills']);
        });

        // Entrepreneur: review job applications to my projects
        Route::get('/projects/{projectId}/job-applications', [JobController::class, 'projectApplications']);
        Route::patch('/job-applications/{id}', [JobController::class, 'reviewApplication']);

        // Government (authenticated)
        Route::post('/gouvernement/appels/{callId}/apply', [GovernmentController::class, 'apply']);
        Route::get('/gouvernement/mes-candidatures', [GovernmentController::class, 'myApplications']);

        // Government-gated endpoints (require role + subscription + KYC)
        // Migrated from `kyc` (legacy IDnorm) to `kyc.smile:verified` — Sprint 4 Smile Identity.
        Route::middleware(['role:government,admin', 'subscribed', 'kyc.smile:verified'])->prefix('gouvernement')->group(function () {
            Route::get('/mes-appels', [GovernmentController::class, 'myCalls']);
            Route::post('/appels', [GovernmentController::class, 'storeCall']);
            Route::patch('/appels/{id}', [GovernmentController::class, 'updateCall']);
            Route::post('/appels/{id}/publish', [GovernmentController::class, 'publishCall']);
            Route::post('/appels/{id}/close', [GovernmentController::class, 'closeCall']);
            Route::post('/appels/{id}/award', [GovernmentController::class, 'awardCall']);
            Route::delete('/appels/{id}', [GovernmentController::class, 'deleteCall']);
            Route::get('/appels/{id}/applications', [GovernmentController::class, 'callApplications']);
            Route::patch('/applications/{id}', [GovernmentController::class, 'reviewApplication']);
            Route::get('/mes-zones', [GovernmentController::class, 'myZones']);
            Route::post('/zones', [GovernmentController::class, 'storeZone']);
            Route::patch('/zones/{id}', [GovernmentController::class, 'updateZone']);
            Route::delete('/zones/{id}', [GovernmentController::class, 'deleteZone']);
        });

        // Admin-gated endpoints
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            // Reads — generous limit, anti-enumeration safety net.
            Route::middleware('throttle:admin-read')->group(function () {
                Route::get('/analytics',     [AdminController::class, 'analytics']);
                Route::get('/users',         [AdminController::class, 'users']);
                Route::get('/users/{id}',    [AdminController::class, 'userShow']);
                Route::get('/trainings',     [AdminController::class, 'trainings']);
                Route::get('/trainings/{id}', [AdminController::class, 'showTraining']);
                Route::get('/moderation',    [AdminController::class, 'moderationQueue']);
                Route::get('/config',        [AdminController::class, 'platformConfig']);
                // Optimisation point 4 — Sectors admin.
                Route::get('/sectors',           [SectorController::class, 'adminIndex']);
                // Optimisation point 5 — Country guides admin.
                Route::get('/country-guides',    [DiasporaController::class, 'adminCountriesIndex']);
                Route::get('/country-guides/{id}', [DiasporaController::class, 'adminCountryShow']);
                // Optimisation point 7 — Government calls admin.
                Route::get('/calls',             [GovernmentController::class, 'adminCallsIndex']);
                Route::get('/calls/{id}',        [GovernmentController::class, 'adminCallShow']);
                // Optimisation point 8 — Economic zones admin.
                Route::get('/zones',             [GovernmentController::class, 'adminZonesIndex']);
                Route::get('/zones/{id}',        [GovernmentController::class, 'adminZoneShow']);
                // Optimisation point 9 — Partners admin.
                Route::get('/partners',          [AdvertisingController::class, 'adminPartnersIndex']);
                Route::get('/partners/{id}',     [AdvertisingController::class, 'adminPartnerShow']);
                // Optimisation point 10 — Testimonials admin.
                Route::get('/testimonials',      [AdvertisingController::class, 'adminTestimonialsIndex']);
                Route::get('/testimonials/{id}', [AdvertisingController::class, 'adminTestimonialShow']);
            });
            // Mutations — tight bucket against runaway scripts / accidental loops.
            Route::middleware('throttle:admin-write')->group(function () {
                Route::patch('/users/{id}',                  [AdminController::class, 'userUpdate']);
                Route::post('/users/{id}/toggle-role',       [AdminController::class, 'userToggleRole']);
                Route::post('/users/{id}/subscription',      [AdminController::class, 'grantSubscription']);
                Route::delete('/users/{id}/subscription',    [AdminController::class, 'revokeSubscription']);
                Route::delete('/users/{id}',                 [AdminController::class, 'destroyUser']);
                Route::delete('/mentors/{id}',               [AdminController::class, 'destroyMentor']);
                Route::post('/trainings',                    [AdminController::class, 'storeTraining']);
                Route::patch('/trainings/{id}',              [AdminController::class, 'updateTraining']);
                Route::delete('/trainings/{id}',             [AdminController::class, 'destroyTraining']);
                Route::post('/moderation/{project}',         [AdminController::class, 'moderateProject']);
                // Optimisation point 4 — Sectors admin.
                Route::post('/sectors',                      [SectorController::class, 'store']);
                Route::patch('/sectors/{id}',                [SectorController::class, 'update']);
                Route::delete('/sectors/{id}',               [SectorController::class, 'destroy']);
                // Optimisation point 5 — Country guides admin.
                Route::post('/country-guides',               [DiasporaController::class, 'adminStoreCountry']);
                Route::patch('/country-guides/{id}',         [DiasporaController::class, 'adminUpdateCountry']);
                Route::delete('/country-guides/{id}',        [DiasporaController::class, 'adminDestroyCountry']);
                // Optimisation point 7 — Government calls admin.
                Route::post('/calls',                        [GovernmentController::class, 'adminStoreCall']);
                Route::patch('/calls/{id}',                  [GovernmentController::class, 'adminUpdateCall']);
                Route::delete('/calls/{id}',                 [GovernmentController::class, 'adminDestroyCall']);
                // Optimisation point 8 — Economic zones admin.
                Route::post('/zones',                        [GovernmentController::class, 'adminStoreZone']);
                Route::patch('/zones/{id}',                  [GovernmentController::class, 'adminUpdateZone']);
                Route::delete('/zones/{id}',                 [GovernmentController::class, 'adminDestroyZone']);
                // Optimisation point 9 — Partners admin.
                Route::post('/partners',                     [AdvertisingController::class, 'adminStorePartner']);
                Route::patch('/partners/{id}',               [AdvertisingController::class, 'adminUpdatePartner']);
                Route::delete('/partners/{id}',              [AdvertisingController::class, 'adminDestroyPartner']);
                // Optimisation point 10 — Testimonials admin.
                Route::post('/testimonials',                 [AdvertisingController::class, 'adminStoreTestimonial']);
                Route::patch('/testimonials/{id}',           [AdvertisingController::class, 'adminUpdateTestimonial']);
                Route::delete('/testimonials/{id}',          [AdvertisingController::class, 'adminDestroyTestimonial']);
                // Generic admin image uploader (reused by Partners, Trainings, etc.).
                Route::post('/uploads/image',                [AdminController::class, 'uploadImage']);
            });
        });

        // Entrepreneur-gated endpoints (require role + subscription + KYC)
        // Migrated from `kyc` (legacy IDnorm) to `kyc.smile:verified` — Sprint 4 Smile Identity.
        Route::middleware(['role:entrepreneur,admin', 'subscribed', 'kyc.smile:verified'])->group(function () {
            Route::post('/projects', [ProjectController::class, 'store']);
            Route::patch('/projects/{project}', [ProjectController::class, 'update']);
            Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
            Route::post('/projects/{project}/publish', [ProjectController::class, 'publish']);
            Route::post('/projects/{project}/updates', [ProjectController::class, 'storeUpdate']);
        });
    });
});

// ─── Google OAuth (Socialite, 2026-05-09) ────────────────────────────
// Declared OUTSIDE the /api prefix so the SPA catch-all below skips them.
// Both routes need session access for state/CSRF + login regenerate.
Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect'])->name('oauth.google.redirect');
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback'])->name('oauth.google.callback');

// SPA catch-all — Vue Router handles routes client-side. The negative
// look-ahead now also excludes /auth/google/* so OAuth handlers above are
// reached instead of falling through to the SPA shell.
Route::get('/{any?}', fn () => view('app'))->where('any', '^(?!api|auth/google).*$');
