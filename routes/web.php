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
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\AdvertisingController;
use App\Http\Controllers\Api\StatsController;
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

    // KYC webhook (public — validated via signature)
    Route::post('/kyc/webhook', [KycController::class, 'webhook']);

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

        // KYC management
        Route::get('/kyc/status', [KycController::class, 'status']);
        Route::get('/kyc/session', [KycController::class, 'sessionData']);
        Route::post('/kyc/step1', [KycController::class, 'saveStep1']);
        Route::post('/kyc/step2', [KycController::class, 'saveStep2']);
        Route::post('/kyc/upload', [KycController::class, 'uploadDocument']);
        Route::post('/kyc/step3', [KycController::class, 'saveStep3']);
        Route::post('/kyc/submit', [KycController::class, 'submitForVerification']);

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
        Route::middleware(['role:government,admin', 'subscribed', 'kyc'])->prefix('gouvernement')->group(function () {
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
            Route::get('/analytics', [AdminController::class, 'analytics']);
            Route::get('/users', [AdminController::class, 'users']);
            Route::get('/users/{id}', [AdminController::class, 'userShow']);
            Route::patch('/users/{id}', [AdminController::class, 'userUpdate']);
            Route::post('/users/{id}/toggle-role', [AdminController::class, 'userToggleRole']);
            Route::get('/moderation', [AdminController::class, 'moderationQueue']);
            Route::post('/moderation/{project}', [AdminController::class, 'moderateProject']);
            Route::get('/config', [AdminController::class, 'platformConfig']);
        });

        // Entrepreneur-gated endpoints (require role + subscription + KYC)
        Route::middleware(['role:entrepreneur,admin', 'subscribed', 'kyc'])->group(function () {
            Route::post('/projects', [ProjectController::class, 'store']);
            Route::patch('/projects/{project}', [ProjectController::class, 'update']);
            Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
            Route::post('/projects/{project}/publish', [ProjectController::class, 'publish']);
            Route::post('/projects/{project}/updates', [ProjectController::class, 'storeUpdate']);
        });
    });
});

// SPA catch-all — Vue Router handles routes client-side
Route::get('/{any?}', fn () => view('app'))->where('any', '^(?!api).*$');
