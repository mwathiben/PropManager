<?php

use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

// Two-Factor Authentication Settings
Route::prefix('two-factor')->name('two-factor.')->group(function () {
    Route::get('/', [TwoFactorController::class, 'index'])->name('index');
    Route::post('/enable', [TwoFactorController::class, 'enable'])->name('enable');
    Route::post('/confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
    Route::post('/disable', [TwoFactorController::class, 'disable'])->name('disable');
    Route::get('/recovery-codes', [TwoFactorController::class, 'showRecoveryCodes'])->name('recovery-codes');
    Route::post('/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes.regenerate');
});

// 1. Onboarding (Multi-Step Wizard)
// Phase-46 ROLE-PATHS-2: 'verified' middleware enforced — landlords
// cannot complete the wizard before clicking the email verification
// link, matching the dashboard.index gate.
Route::prefix('onboarding')->middleware('verified')->name('onboarding.')->group(function () {
    Route::get('/', [OnboardingController::class, 'index'])->name('index');
    Route::get('/step/{step}', [OnboardingController::class, 'step'])->name('step');
    Route::post('/step/{step}', [OnboardingController::class, 'saveStep'])->name('step.save');
    Route::post('/step/{step}/skip', [OnboardingController::class, 'skip'])->name('step.skip');
    Route::post('/complete', [OnboardingController::class, 'complete'])->name('complete');
    Route::post('/reset', [OnboardingController::class, 'reset'])->name('reset');
    Route::get('/progress', [OnboardingController::class, 'getProgress'])->name('progress');
    Route::post('/profile-photo', [OnboardingController::class, 'uploadProfilePhoto'])
        ->middleware('throttle:file-upload')
        ->name('profile-photo');
});
// Phase-31 ONB-WIZARD-2: dashboard ResumeBanner status feed
Route::get('/api/onboarding/status', [\App\Http\Controllers\Onboarding\OnboardingResumeController::class, 'status'])
    ->name('onboarding.status');

// Phase-46 PROGRESS-RESUME-1: signed-URL resume entrypoint. Sits inside
// the 'auth' group so an unauthenticated hit redirects to login (the
// resume controller will pick up where the user left off after they
// sign back in). The 'signed' middleware verifies Laravel's URL
// signature; OnboardingResumeService::consume() handles replay defence.
Route::get('/onboarding/resume/{session}', \App\Http\Controllers\Onboarding\OnboardingResumeRedirectController::class)
    ->middleware('signed')
    ->name('onboarding.resume');

// Phase-31 ONB-SAMPLE-2: prospect demo dataset toggle
Route::middleware('role:landlord')->group(function () {
    Route::post('/onboarding/sample-data/populate', [\App\Http\Controllers\Onboarding\SampleDataController::class, 'populate'])
        ->name('onboarding.sample.populate');
    Route::post('/onboarding/sample-data/reset', [\App\Http\Controllers\Onboarding\SampleDataController::class, 'reset'])
        ->name('onboarding.sample.reset');
});

// Phase-31 ONB-HELP-2/3: HelpDrawer backing endpoints. Phase-38
// DEFER-ROUTE-CONFLICT-1: renamed from help.{contextual,search}
// to help.api.* to free the legacy public help portal's name —
// duplicate help.search names broke `php artisan route:cache`.
// HelpDrawer.vue uses hardcoded /api/help/* URLs, not route(),
// so no JS consumer changes needed.
Route::get('/api/help/contextual', [\App\Http\Controllers\Onboarding\HelpSearchController::class, 'contextual'])
    ->name('help.api.contextual');
Route::get('/api/help/search', [\App\Http\Controllers\Onboarding\HelpSearchController::class, 'search'])
    ->name('help.api.search');

// Phase-31 ONB-EMPTY-1/3: milestone-checklist surface + dismiss flag
Route::get('/api/onboarding/milestones', [\App\Http\Controllers\Onboarding\MilestoneStatusController::class, 'status'])
    ->name('onboarding.milestones.status');
Route::post('/api/onboarding/checklist/dismiss', [\App\Http\Controllers\Onboarding\MilestoneStatusController::class, 'dismiss'])
    ->name('onboarding.checklist.dismiss');

// Phase-66 ONBOARDING-TOUR-2: server-authoritative tour progress.
// tour_key is derived from role in the controller (never client-sent);
// throttled so a tampered client can't hammer the state writes.
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/onboarding-tour/advance', [\App\Http\Controllers\Onboarding\OnboardingTourController::class, 'advance'])
        ->name('onboarding-tour.advance');
    Route::post('/onboarding-tour/complete', [\App\Http\Controllers\Onboarding\OnboardingTourController::class, 'complete'])
        ->name('onboarding-tour.complete');
    Route::post('/onboarding-tour/dismiss', [\App\Http\Controllers\Onboarding\OnboardingTourController::class, 'dismiss'])
        ->name('onboarding-tour.dismiss');
});

// Phase-32 SRE-INCIDENT-2: operational incident CRUD (super_admin only)
Route::middleware('role:super_admin')->group(function () {
    Route::get('/ops/incidents', [\App\Http\Controllers\Sre\OpsIncidentController::class, 'index'])
        ->name('ops.incidents.index');
    Route::post('/ops/incidents', [\App\Http\Controllers\Sre\OpsIncidentController::class, 'store'])
        ->name('ops.incidents.store');
    Route::post('/ops/incidents/{incident}/status', [\App\Http\Controllers\Sre\OpsIncidentController::class, 'setStatus'])
        ->name('ops.incidents.set-status');
    Route::post('/ops/incidents/{incident}/post-mortem', [\App\Http\Controllers\Sre\OpsIncidentController::class, 'setPostMortem'])
        ->name('ops.incidents.post-mortem');

    // Phase-33 COST-ATTRIB-3: top-N costliest landlords for ops dashboard
    Route::get('/ops/landlord-cost', [\App\Http\Controllers\Cost\LandlordCostController::class, 'topN'])
        ->name('ops.landlord-cost.top-n');

    // Phase-34 GROWTH-MRR-3: MRR trend + per-plan breakdown
    Route::get('/ops/mrr', [\App\Http\Controllers\Growth\MrrController::class, 'trend'])
        ->name('ops.mrr.trend');

    // Phase-36 INSIGHT-OPS-2: super-admin operator dashboard
    Route::get('/ops', [\App\Http\Controllers\Insight\OpsDashboardController::class, 'index'])
        ->name('ops.index');

    // Phase-36 INSIGHT-EXPORTS-1: MRR snapshot xlsx download
    Route::get('/ops/mrr/export', [\App\Http\Controllers\Insight\MrrExportController::class, 'export'])
        ->name('ops.mrr.export');

    // Phase-39 PUSH-EXTEND-3: super_admin manual push test runner.
    Route::get('/ops/push', [\App\Http\Controllers\Ops\PushTestRunnerController::class, 'show'])
        ->name('ops.push.show');
    Route::post('/ops/push', [\App\Http\Controllers\Ops\PushTestRunnerController::class, 'send'])
        ->name('ops.push.send');

    // Phase-39 RETENTION-READ-3: archive search + rehydrate UI.
    Route::get('/ops/archive/search', [\App\Http\Controllers\Ops\ArchiveSearchController::class, 'show'])
        ->name('ops.archive.show');
    Route::post('/ops/archive/rehydrate', [\App\Http\Controllers\Ops\ArchiveSearchController::class, 'rehydrate'])
        ->name('ops.archive.rehydrate');

    // Phase-37 PWA-FRONTEND-ADMIN-2/3: experiments admin CRUD
    Route::get('/ops/experiments', [\App\Http\Controllers\Ops\ExperimentController::class, 'index'])
        ->name('ops.experiments.index');
    Route::post('/ops/experiments', [\App\Http\Controllers\Ops\ExperimentController::class, 'store'])
        ->name('ops.experiments.store');
    Route::get('/ops/experiments/{experiment}', [\App\Http\Controllers\Ops\ExperimentController::class, 'show'])
        ->name('ops.experiments.show');
    Route::patch('/ops/experiments/{experiment}', [\App\Http\Controllers\Ops\ExperimentController::class, 'update'])
        ->name('ops.experiments.update');
    Route::post('/ops/experiments/{experiment}/conclude', [\App\Http\Controllers\Ops\ExperimentController::class, 'conclude'])
        ->name('ops.experiments.conclude');

    // Phase-56 DASHBOARDS-2: attribution + funnel sankey + cohort-by-source + auto-promote timeline
    Route::get('/ops/growth/attribution', [\App\Http\Controllers\Ops\OpsGrowthAttributionController::class, 'index'])
        ->name('ops.growth.attribution.index');

    // Phase-66 REFERRAL-LEADERBOARD-2: super-admin board with full names.
    Route::get('/ops/growth/referral-leaderboard', [\App\Http\Controllers\Ops\OpsReferralLeaderboardController::class, 'index'])
        ->name('ops.growth.referral-leaderboard.index');

    // Phase-66 COHORT-RETENTION-2: per-source retention vs organic baseline.
    Route::get('/ops/growth/cohort-retention', [\App\Http\Controllers\Ops\OpsCohortRetentionController::class, 'index'])
        ->name('ops.growth.cohort-retention.index');

    // Phase-77 FUNNEL-2 / INVITE-FUNNEL-2: onboarding-health dashboard.
    Route::get('/ops/onboarding/funnel', [\App\Http\Controllers\Ops\OpsOnboardingFunnelController::class, 'index'])
        ->name('ops.onboarding.funnel');
});

// Phase-66 REFERRAL-LEADERBOARD-2/3: landlord-facing anonymised
// leaderboard + DPA opt-out toggle. auth+verified (any verified
// account that can refer) — deliberately NOT super_admin gated.
Route::middleware('verified')->group(function () {
    Route::get('/growth/leaderboard', [\App\Http\Controllers\Growth\ReferralLeaderboardController::class, 'index'])
        ->name('growth.leaderboard');
    Route::post('/growth/leaderboard/opt-out', \App\Http\Controllers\Growth\LeaderboardOptOutController::class)
        ->middleware('throttle:30,1')
        ->name('growth.leaderboard.opt-out');
});

// Phase-34 GROWTH-REFERRAL-2: landlord self-serve referral surface.
// NOT super_admin gated — every landlord sees their own code +
// their own ledger.
Route::middleware('role:landlord')->group(function () {
    Route::post('/referrals/redeem', [\App\Http\Controllers\Growth\ReferralController::class, 'redeem'])
        ->name('referrals.redeem');
    Route::get('/referrals/mine', [\App\Http\Controllers\Growth\ReferralController::class, 'mine'])
        ->name('referrals.mine');

    // Phase-36 INSIGHT-LANDLORD-3: deeper-dive growth surface
    Route::get('/growth', [\App\Http\Controllers\Insight\LandlordGrowthController::class, 'index'])
        ->name('landlord.growth');

    // Phase-37 PWA-FRONTEND-ADMIN-1: notification preferences page
    // backed by Settings\NotificationPreferenceController::page.
    Route::get('/settings/notifications', [
        \App\Http\Controllers\Settings\NotificationPreferenceController::class,
        'page',
    ])->name('settings.notifications');
});
// Legacy routes for backward compatibility
Route::get('/onboarding/create', [OnboardingController::class, 'create'])->name('onboarding.create');
Route::post('/onboarding/store', [OnboardingController::class, 'store'])->name('onboarding.store');
