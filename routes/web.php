<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\AdminBillingController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ArchiveHubController;
use App\Http\Controllers\ArrearsController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Finance\DepositController as FinanceDepositController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\FinanceNotificationController;
use App\Http\Controllers\Finance\FinanceReportController;
use App\Http\Controllers\Finance\FinanceSettingsController;
use App\Http\Controllers\Finance\FinanceTemplateController;
use App\Http\Controllers\Finance\LateFeeController;
use App\Http\Controllers\FinancesController;
use App\Http\Controllers\GdprController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceSettingController;
use App\Http\Controllers\InvoiceTemplateController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\MaintenanceHubController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OperationsHubController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentLinkController;
use App\Http\Controllers\PaymentsHubController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptTemplateController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantEmergencyContactController;
use App\Http\Controllers\TenantFinancesController;
use App\Http\Controllers\TenantInvitationController;
use App\Http\Controllers\TenantNoteController;
use App\Http\Controllers\TenantPaymentVerificationController;
use App\Http\Controllers\TenantPortalController;
use App\Http\Controllers\TenantsHubController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\WaterHubController;
use App\Http\Controllers\WaterReadingController;
use App\Http\Controllers\WaterSettingsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Phase-21 DEFER-AUTHZ-4: client-side route-guard landing page. The
// Inertia router.beforeEach hook in resources/js/app.js redirects here
// when the shared abilities map predicts the server will reject the
// visit — so the user sees the 403.vue UX without a wasted round-trip.
// Real server-side 403/404s render the same pages via bootstrap/app.php.
Route::get('/403', fn () => Inertia::render('Errors/403'))->name('errors.403');

// Phase-26 PWA-SHELL-2: branded offline page. The service worker's
// navigation fallback (resources/js/sw-merged.ts NavigationRoute)
// serves this when the network is unreachable. Rendered as an Inertia
// page so it shares layout chrome.
Route::get('/offline', fn () => Inertia::render('Offline'))->name('offline');

// Phase-26 PWA-SHELL-1: service worker at root scope. Vite's
// `vite-plugin-pwa` generates the SW into public/build/sw.js. Browsers
// limit a SW's scope to its own URL path by default — to grant root
// scope from a non-root path we must send `Service-Worker-Allowed: /`.
// Keeping the registration path `/sw.js` (unchanged from pre-Phase-26)
// means no client-side change. If the build hasn't run yet (fresh
// clone), this route 404s and the SW registration in app.js fails
// silently — non-fatal.
Route::get('/sw.js', function () {
    $path = public_path('build/sw.js');
    if (! is_file($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/javascript',
        'Service-Worker-Allowed' => '/',
        'Cache-Control' => 'no-cache, must-revalidate',
    ]);
})->name('sw.js');

// --- PUBLIC INVITATION ROUTES (Guest Access) ---
// Caretaker Invitations
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->middleware('throttle:invitation')
    ->name('invitations.accept');

// Tenant Invitations
Route::get('/tenant-invite/{token}', [TenantInvitationController::class, 'show'])->name('tenant-invitations.show');
Route::post('/tenant-invite/{token}/accept', [TenantInvitationController::class, 'accept'])
    ->middleware('throttle:invitation')
    ->name('tenant-invitations.accept');

// Payment Links (WhatsApp/SMS clickable links)
Route::get('/pay/{token}', [PaymentLinkController::class, 'show'])
    ->middleware('throttle:payment-link')
    ->name('payment.link');

// --- TWO-FACTOR AUTHENTICATION CHALLENGE (Post-Login) ---
Route::get('/two-factor-challenge', [TwoFactorController::class, 'challenge'])
    ->name('two-factor.challenge');
Route::post('/two-factor-challenge', [TwoFactorController::class, 'verifyChallenge'])
    ->middleware('throttle:two-factor')
    ->name('two-factor.verify');

// --- PAYMENT WEBHOOKS (Server-to-Server, CSRF excluded) ---
Route::post('/webhooks/paystack', [PaymentController::class, 'handleWebhook'])
    ->middleware('webhook.paystack')
    ->name('webhooks.paystack');

// Phase-30 INT-MPESA-DEEP-3: HMAC-SHA512 verified Paystack receiver.
Route::post('/webhooks/v2/paystack', [\App\Http\Controllers\Webhooks\PaystackWebhookController::class, 'handle'])
    ->name('webhooks.v2.paystack');

// Phase-40 GATEWAY-WEBHOOK-2: Stripe-Signature verified Stripe receiver.
Route::post('/webhooks/v2/stripe', \App\Http\Controllers\Webhooks\StripeWebhookController::class)
    ->name('webhooks.v2.stripe');

Route::prefix('webhooks/mpesa')->name('webhooks.mpesa.')->middleware('webhook.mpesa')->group(function () {
    Route::post('/stk-callback', [\App\Http\Controllers\Api\MpesaWebhookController::class, 'stkCallback'])
        ->name('stk-callback');
    Route::post('/c2b/validation', [\App\Http\Controllers\Api\MpesaWebhookController::class, 'c2bValidation'])
        ->name('c2b-validation');
    Route::post('/c2b/confirmation', [\App\Http\Controllers\Api\MpesaWebhookController::class, 'c2bConfirmation'])
        ->name('c2b-confirmation');
});

// --- THE DASHBOARD (Role-Based) ---
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'payment.verified', 'kyc.complete'])
    ->name('dashboard');

Route::get('/units/{unit}/detail', [DashboardController::class, 'unitDetail'])
    ->middleware(['auth'])
    ->name('units.detail');

Route::get('/dashboard/stats', \App\Http\Controllers\DashboardStatsController::class)
    ->middleware(['auth', 'role:landlord,caretaker', 'throttle:30,1'])
    ->withoutMiddleware([\App\Http\Middleware\HandleInertiaRequests::class])
    ->name('dashboard.stats');

// --- SIGNED EMAIL ROUTES (no auth required) ---
// RATE-9: signed.once replaces 'signed' so a forwarded email link
// cannot be replayed. The first hit consumes the signature in
// signed_link_uses; subsequent hits return 403.
Route::get('/email/preferences', [\App\Http\Controllers\NotificationsController::class, 'emailPreferences'])
    ->name('email.preferences')
    ->middleware(['signed.once', 'throttle:invitation']);

Route::post('/email/unsubscribe', [\App\Http\Controllers\NotificationsController::class, 'oneClickUnsubscribe'])
    ->name('email.unsubscribe')
    ->middleware(['signed.once', 'throttle:invitation']);

// Phase-59 SIGNED-URLS-1: local-driver fallback target for
// TenantDiskResolver::temporaryUrl. The signed middleware verifies
// the URL hasn't been tampered with; controllers issue these via
// $resolver->temporaryUrl() after their own ownership-validation pass.
Route::get('/files/local-stream', [\App\Http\Controllers\FileLocalStreamController::class, 'stream'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('files.local-stream');

// Phase-54 VENDOR-ONBOARDING-2: signed-URL profile completion for a
// vendor. No auth — the signed URL IS the auth. Outside the
// auth-middleware group so unauthenticated vendors can complete the
// form. throttle:invitation matches the existing one-shot-link cadence.
Route::middleware(['signed', 'throttle:invitation'])->group(function () {
    Route::get('/v/profile/{vendor}', [\App\Http\Controllers\VendorProfileController::class, 'edit'])
        ->name('vendor.profile.edit');
    Route::patch('/v/profile/{vendor}', [\App\Http\Controllers\VendorProfileController::class, 'update'])
        ->name('vendor.profile.update');
});

// --- AUTHENTICATED ROUTES GROUP ---
Route::middleware('auth')->group(function () {

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

    // 2. Leases (Adding Tenants & Rent Hikes)
    Route::get('/units/{unit}/lease/create', [LeaseController::class, 'create'])->name('leases.create');
    Route::post('/units/{unit}/lease', [LeaseController::class, 'store'])->name('leases.store');
    Route::post('/leases/{lease}/adjust-rent', [LeaseController::class, 'adjustRent'])->name('leases.adjust-rent');
    Route::post('/leases/batch-adjust', [LeaseController::class, 'batchAdjustRent'])->name('leases.batch-adjust');
    Route::post('/leases/{lease}/wallet-adjustment', [LeaseController::class, 'walletAdjustment'])->name('leases.wallet-adjustment');
    Route::get('/leases/{lease}/wallet-history', [LeaseController::class, 'walletHistory'])->name('leases.wallet-history');
    Route::get('/leases/{lease}', [LeaseController::class, 'show'])->name('leases.show');
    Route::get('/leases/{lease}/download', [LeaseController::class, 'download'])
        ->middleware('throttle:pdf-render')
        ->name('leases.download');
    // Phase-61 TERMINATION-3: lease early-termination route.
    Route::post('/leases/{lease}/terminate', [LeaseController::class, 'terminate'])
        ->name('leases.terminate');
    // Phase-61 TRANSFER-3: lease assignment / sublet route.
    Route::post('/leases/{lease}/transfer', [LeaseController::class, 'transfer'])
        ->name('leases.transfer');
    // Phase-61 PAUSE-3: temporary lease pause route.
    Route::post('/leases/{lease}/pause', [LeaseController::class, 'pause'])
        ->name('leases.pause');
    // Phase-61 RENEWAL-AUTO-3: per-lease auto-renew toggle.
    Route::patch('/leases/{lease}/auto-renew', [LeaseController::class, 'toggleAutoRenew'])
        ->name('leases.auto-renew');

    // 3. The Architect (Building Configuration)
    Route::get('/buildings/{building}/configure', [BuildingController::class, 'edit'])->name('buildings.edit');
    Route::put('/buildings/{building}/settings', [BuildingController::class, 'updateSettings'])->name('buildings.update-settings');
    Route::post('/buildings/{building}/update-units', [BuildingController::class, 'updateUnits'])->name('buildings.update-units');
    Route::post('/buildings/{building}/add-unit', [BuildingController::class, 'addUnit'])->name('buildings.add-unit');

    // Route for adding a new wing/building
    Route::post('/properties/{property}/buildings', [BuildingController::class, 'store'])->name('buildings.store');

    // Route for adding a wing to an existing building
    Route::post('/buildings/{building}/wings', [BuildingController::class, 'storeWing'])->name('buildings.store-wing');

    // Buildings (Landlord Home - New Primary Navigation)
    Route::get('/buildings', [BuildingController::class, 'index'])->name('buildings.index');
    Route::post('/buildings', [BuildingController::class, 'storeStandalone'])->name('buildings.storeStandalone');

    // Properties (Legacy redirect + store for backward compatibility)
    Route::get('/properties', fn () => redirect()->route('buildings.index'))->name('properties.index');
    Route::post('/properties', [\App\Http\Controllers\PropertyController::class, 'store'])->name('properties.store');

    // Building Details & Dashboard
    Route::get('/buildings/{building}', [BuildingController::class, 'show'])->name('buildings.show');
    Route::get('/buildings/{building}/dashboard', [BuildingController::class, 'dashboard'])->name('buildings.dashboard');

    // Water Settings (Per-Building Configuration)
    Route::get('/buildings/{building}/water-settings', [BuildingController::class, 'waterSettings'])->name('buildings.water-settings');
    Route::put('/buildings/{building}/water-settings', [BuildingController::class, 'updateWaterSettings'])->name('buildings.water-settings.update');

    // Invoice Automation Settings (Per-Building Configuration)
    Route::put('/buildings/{building}/automation-settings', [BuildingController::class, 'updateAutomationSettings'])->name('buildings.automation-settings.update');

    // Building Deletion
    Route::delete('/buildings/{building}', [BuildingController::class, 'destroy'])->name('buildings.destroy');

    // ========================================
    // CONSOLIDATED HUB ROUTES (Navigation Optimization)
    // ========================================

    // Tenants Hub - Consolidates: Tenants, Invitations, Verifications, Payment Verifications, Move-Outs, History
    Route::get('/tenants-hub', [TenantsHubController::class, 'index'])->name('tenants.hub');

    // Maintenance Hub - Consolidates: Tickets (Issues), Complaints
    Route::get('/maintenance', [MaintenanceHubController::class, 'index'])->name('maintenance.hub');

    // Water Hub - Consolidates: Readings, History, Settings
    Route::get('/water', [WaterHubController::class, 'index'])->name('water.hub');

    // Archive Hub - Consolidates: Documents, Leases, Activity Logs
    Route::get('/archive', [ArchiveHubController::class, 'index'])->name('archive.hub');

    // Operations Hub - Consolidates: Notifications, Bulk Operations, Team, Imports
    Route::get('/operations', [OperationsHubController::class, 'index'])->name('operations.hub');

    // ========================================
    // END CONSOLIDATED HUB ROUTES
    // ========================================

    // 4. Tenant Management (Viewing/Editing Profiles)
    // PRIV-15: defense-in-depth role guard. Controllers already verify
    // landlord scope inline, but the route-level role:landlord,caretaker
    // means a misconfigured controller (or a future regression) cannot
    // accidentally expose these endpoints to a tenant role token.
    Route::middleware('role:landlord,caretaker')->group(function () {
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::get('/tenants/{tenant}/modal-data', [TenantController::class, 'modalData'])->name('tenants.modal-data');
        Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        // Tenant Notes
        Route::post('/tenants/{tenant}/notes', [TenantNoteController::class, 'store'])->name('tenants.notes.store');
        Route::put('/tenant-notes/{note}', [TenantNoteController::class, 'update'])->name('tenants.notes.update');
        Route::delete('/tenant-notes/{note}', [TenantNoteController::class, 'destroy'])->name('tenants.notes.destroy');
        // Emergency Contacts
        Route::post('/tenants/{tenant}/emergency-contacts', [TenantEmergencyContactController::class, 'store'])->name('tenants.emergency-contacts.store');
        Route::put('/emergency-contacts/{contact}', [TenantEmergencyContactController::class, 'update'])->name('tenants.emergency-contacts.update');
        Route::delete('/emergency-contacts/{contact}', [TenantEmergencyContactController::class, 'destroy'])->name('tenants.emergency-contacts.destroy');
        // Tenant API for payment recording
        Route::get('/tenants/search', [TenantController::class, 'search'])
            ->middleware('throttle:search')
            ->name('tenants.search');
        Route::get('/tenants/{tenant}/outstanding-invoices', [TenantController::class, 'outstandingInvoices'])->name('tenants.outstanding-invoices');
        Route::get('/tenants/{tenant}/refundable-payments', [TenantController::class, 'refundablePayments'])->name('tenants.refundable-payments');
        // Tenant Ledger/Statement
        Route::get('/tenants/{tenant}/ledger', [TenantController::class, 'ledger'])->name('tenants.ledger');
        Route::get('/tenants/{tenant}/ledger/pdf', [TenantController::class, 'ledgerPdf'])
            ->middleware('throttle:pdf-render')
            ->name('tenants.ledger.pdf');
        Route::post('/tenants/{tenant}/ledger/email', [TenantController::class, 'ledgerEmail'])->name('tenants.ledger.email');
    });

    // 5. Water Readings (The Water Guy)
    Route::get('/readings', [WaterReadingController::class, 'index'])->name('readings.index');
    Route::post('/readings', [WaterReadingController::class, 'store'])
        ->middleware('throttle:file-upload')
        ->name('readings.store');
    Route::get('/readings/history', [WaterReadingController::class, 'history'])->name('readings.history');
    Route::get('/readings/review', [WaterReadingController::class, 'review'])->name('readings.review');
    Route::post('/readings/{reading}/approve', [WaterReadingController::class, 'approve'])->name('readings.approve');
    Route::post('/readings/{reading}/reject', [WaterReadingController::class, 'reject'])->name('readings.reject');
    Route::get('/readings/{reading}/photo', [WaterReadingController::class, 'photo'])->name('readings.photo');
    Route::put('/readings/{reading}', [WaterReadingController::class, 'update'])->name('readings.update');
    Route::delete('/readings/{reading}', [WaterReadingController::class, 'destroy'])->name('readings.destroy');

    // 6. Invitations (Caretaker Management)
    Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
    Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

    // 6a. Caretaker Invitation Accept/Decline (In-App for existing users)
    Route::post('/invitations/{invitation}/accept-authenticated', [InvitationController::class, 'acceptAuthenticated'])->name('invitations.accept-authenticated');
    Route::post('/invitations/{invitation}/decline-authenticated', [InvitationController::class, 'declineAuthenticated'])->name('invitations.decline-authenticated');

    // 6b. Tenant Invitations (Tenant Onboarding)
    Route::get('/tenant-invitations', [TenantInvitationController::class, 'index'])->name('tenant-invitations.index');
    Route::post('/tenant-invitations', [TenantInvitationController::class, 'store'])->name('tenant-invitations.store');
    Route::put('/tenant-invitations/{invitation}', [TenantInvitationController::class, 'update'])->name('tenant-invitations.update');
    Route::post('/tenant-invitations/{invitation}/resend', [TenantInvitationController::class, 'resend'])->name('tenant-invitations.resend');
    Route::delete('/tenant-invitations/{invitation}', [TenantInvitationController::class, 'destroy'])->name('tenant-invitations.destroy');

    // 6c. Tenant Invitation Accept/Decline (In-App for existing users)
    Route::post('/tenant-invitations/{invitation}/accept-authenticated', [TenantInvitationController::class, 'acceptAuthenticated'])->name('tenant-invitations.accept-authenticated');
    Route::post('/tenant-invitations/{invitation}/decline-authenticated', [TenantInvitationController::class, 'declineAuthenticated'])->name('tenant-invitations.decline-authenticated');

    // 6c2. Inbox (Tenant Messages from WhatsApp/SMS)
    Route::prefix('inbox')->name('inbox.')->group(function () {
        Route::get('/', [InboxController::class, 'index'])->name('index');
        Route::put('/mark-all-read', [InboxController::class, 'markAllAsRead'])->name('mark-all-read');
        Route::get('/{message}', [InboxController::class, 'show'])->name('show');
        Route::post('/{message}/reply', [InboxController::class, 'reply'])
            ->middleware('throttle:inbox-reply') // RATE-5
            ->name('reply');
        Route::put('/{message}/read', [InboxController::class, 'markAsRead'])->name('mark-read');
    });

    // 6d. Payment Verification System (Initial Payment Verification for New Tenants)
    Route::prefix('payment-verifications')->name('payment-verifications.')->group(function () {
        Route::get('/', [TenantPaymentVerificationController::class, 'index'])->name('index');
        Route::get('/{verification}', [TenantPaymentVerificationController::class, 'show'])->name('show');
        Route::post('/{verification}/approve', [TenantPaymentVerificationController::class, 'approve'])->name('approve');
        Route::post('/{verification}/reject', [TenantPaymentVerificationController::class, 'reject'])->name('reject');
    });

    // 6e. Verification System (Tenant Document Verification)
    Route::get('/verifications', [\App\Http\Controllers\VerificationController::class, 'index'])->name('verifications.index');
    Route::post('/verifications/templates', [\App\Http\Controllers\VerificationController::class, 'storeTemplate'])->name('verifications.templates.store');
    Route::put('/verifications/templates/{template}', [\App\Http\Controllers\VerificationController::class, 'updateTemplate'])->name('verifications.templates.update');
    Route::delete('/verifications/templates/{template}', [\App\Http\Controllers\VerificationController::class, 'destroyTemplate'])->name('verifications.templates.destroy');
    Route::get('/leases/{lease}/verification', [\App\Http\Controllers\VerificationController::class, 'showLeaseVerification'])->name('verifications.lease');
    Route::post('/leases/{lease}/verification/start', [\App\Http\Controllers\VerificationController::class, 'startVerification'])->name('verifications.start');
    Route::put('/verifications/{verification}', [\App\Http\Controllers\VerificationController::class, 'updateVerification'])->name('verifications.update');
    Route::post('/leases/{lease}/verification/bulk-update', [\App\Http\Controllers\VerificationController::class, 'bulkUpdateVerifications'])->name('verifications.bulkUpdate');
    Route::post('/leases/{lease}/verification/reset', [\App\Http\Controllers\VerificationController::class, 'resetVerification'])->name('verifications.reset');
    Route::post('/leases/{lease}/verification/complete', [\App\Http\Controllers\VerificationController::class, 'completeVerification'])->name('verifications.complete');
    Route::get('/leases/{lease}/verification/status', [\App\Http\Controllers\VerificationController::class, 'getVerificationStatus'])->name('verifications.status');

    // 6e. Move-Out Workflow
    Route::get('/move-outs', [\App\Http\Controllers\MoveOutController::class, 'index'])->name('move-outs.index');
    Route::get('/leases/{lease}/move-out/create', [\App\Http\Controllers\MoveOutController::class, 'create'])->name('move-outs.create');
    Route::post('/leases/{lease}/move-out', [\App\Http\Controllers\MoveOutController::class, 'store'])->name('move-outs.store');
    Route::get('/move-outs/{moveOut}', [\App\Http\Controllers\MoveOutController::class, 'show'])->name('move-outs.show');
    Route::put('/move-outs/{moveOut}', [\App\Http\Controllers\MoveOutController::class, 'update'])->name('move-outs.update');
    Route::post('/move-outs/{moveOut}/start-inspection', [\App\Http\Controllers\MoveOutController::class, 'startInspection'])->name('move-outs.start-inspection');
    Route::post('/move-outs/{moveOut}/deductions', [\App\Http\Controllers\MoveOutController::class, 'addDeduction'])
        ->middleware('throttle:file-upload')
        ->name('move-outs.deductions.store');
    Route::put('/move-out-deductions/{deduction}', [\App\Http\Controllers\MoveOutController::class, 'updateDeduction'])->name('move-outs.deductions.update');
    Route::delete('/move-out-deductions/{deduction}', [\App\Http\Controllers\MoveOutController::class, 'deleteDeduction'])->name('move-outs.deductions.destroy');
    Route::get('/move-out-deductions/{deduction}/photo', [\App\Http\Controllers\MoveOutController::class, 'deductionPhoto'])->name('move-outs.deductions.photo');
    Route::post('/move-outs/{moveOut}/complete-inspection', [\App\Http\Controllers\MoveOutController::class, 'completeInspection'])->name('move-outs.complete-inspection');
    Route::post('/move-outs/{moveOut}/complete', [\App\Http\Controllers\MoveOutController::class, 'complete'])->name('move-outs.complete');
    Route::post('/move-outs/{moveOut}/cancel', [\App\Http\Controllers\MoveOutController::class, 'cancel'])->name('move-outs.cancel');

    // 6f. Move-Out Deduction Categories
    Route::resource('move-out-categories', \App\Http\Controllers\MoveOutDeductionCategoryController::class)
        ->except(['create', 'edit', 'show']);

    // 7. Documents (File Management)
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])
        ->middleware('throttle:file-upload')
        ->name('documents.store');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/view', [DocumentController::class, 'view'])->name('documents.view');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('/documents/for-model', [DocumentController::class, 'forModel'])->name('documents.forModel');

    // 8. Invoices
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
    Route::put('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.updateStatus');
    Route::post('/invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment'])
        ->name('invoices.recordPayment');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::post('/invoices/{invoice}/send-reminder', [InvoiceController::class, 'sendReminder'])
        ->middleware('throttle:notification-send') // RATE-2
        ->name('invoices.send-reminder');
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])
        ->middleware('throttle:pdf-render')
        ->name('invoices.download');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
    Route::get('/invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
    Route::post('/invoices/{invoice}/reissue', [InvoiceController::class, 'reissue'])->name('invoices.reissue');

    // Invoice Settings
    Route::get('/invoice-settings', [InvoiceSettingController::class, 'edit'])->name('invoice-settings.edit');
    Route::put('/invoice-settings', [InvoiceSettingController::class, 'update'])->name('invoice-settings.update');
    Route::post('/invoice-settings/logo', [InvoiceSettingController::class, 'uploadLogo'])->name('invoice-settings.upload-logo');
    Route::delete('/invoice-settings/logo', [InvoiceSettingController::class, 'removeLogo'])->name('invoice-settings.remove-logo');

    // Invoice Templates (index redirects to Finance Hub)
    Route::get('/invoice-templates', fn () => redirect()->route('finances.templates.invoices'))->name('invoice-templates.index');
    Route::get('/invoice-templates/create', [InvoiceTemplateController::class, 'create'])->name('invoice-templates.create');
    Route::post('/invoice-templates', [InvoiceTemplateController::class, 'store'])->name('invoice-templates.store');
    Route::get('/invoice-templates/{invoiceTemplate}/edit', [InvoiceTemplateController::class, 'edit'])->name('invoice-templates.edit');
    Route::put('/invoice-templates/{invoiceTemplate}', [InvoiceTemplateController::class, 'update'])->name('invoice-templates.update');
    Route::delete('/invoice-templates/{invoiceTemplate}', [InvoiceTemplateController::class, 'destroy'])->name('invoice-templates.destroy');
    Route::post('/invoice-templates/{invoiceTemplate}/set-default', [InvoiceTemplateController::class, 'setDefault'])->name('invoice-templates.set-default');

    // Receipt Templates (index redirects to Finance Hub)
    Route::get('/receipt-templates', fn () => redirect()->route('finances.templates.receipts'))->name('receipt-templates.index');
    Route::get('/receipt-templates/create', [ReceiptTemplateController::class, 'create'])->name('receipt-templates.create');
    Route::post('/receipt-templates', [ReceiptTemplateController::class, 'store'])->name('receipt-templates.store');
    Route::get('/receipt-templates/{receiptTemplate}/edit', [ReceiptTemplateController::class, 'edit'])->name('receipt-templates.edit');
    Route::put('/receipt-templates/{receiptTemplate}', [ReceiptTemplateController::class, 'update'])->name('receipt-templates.update');
    Route::delete('/receipt-templates/{receiptTemplate}', [ReceiptTemplateController::class, 'destroy'])->name('receipt-templates.destroy');
    Route::post('/receipt-templates/{receiptTemplate}/set-default', [ReceiptTemplateController::class, 'setDefault'])->name('receipt-templates.set-default');

    // Payments (Paystack)
    Route::post('/invoices/{invoice}/paystack/initialize', [PaymentController::class, 'initializePaystack'])
        ->middleware('throttle:payment')
        ->name('payments.paystack.initialize');

    // Phase-41 GATEWAY-CHECKOUT-2: gateway-agnostic checkout (Paystack OR Stripe).
    Route::post('/invoices/{invoice}/checkout/initialize', [PaymentController::class, 'initializeCheckout'])
        ->middleware('throttle:payment')
        ->name('payments.checkout.initialize');
    Route::get('/payments/callback', [PaymentController::class, 'handleCallback'])->name('payments.callback');
    Route::get('/payments/public-key', [PaymentController::class, 'getPublicKey'])->name('payments.publicKey');
    Route::get('/payments/{payment}', [\App\Http\Controllers\PaymentDetailController::class, 'show'])->name('payments.detail.show');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'downloadReceipt'])->name('payments.downloadReceipt');
    Route::post('/payments/{payment}/send-receipt', [PaymentController::class, 'sendReceipt'])
        ->middleware('throttle:notification-send') // RATE-2
        ->name('payments.send-receipt');
    Route::post('/payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');

    // Refunds
    Route::get('/refunds', [\App\Http\Controllers\RefundController::class, 'index'])->name('refunds.index');
    Route::get('/payments/{payment}/refund', [\App\Http\Controllers\RefundController::class, 'create'])->name('refunds.create');
    Route::post('/payments/{payment}/refund', [\App\Http\Controllers\RefundController::class, 'store'])->name('refunds.store');
    Route::get('/refunds/{refund}', [\App\Http\Controllers\RefundController::class, 'show'])->name('refunds.show');
    Route::post('/refunds/{refund}/process', [\App\Http\Controllers\RefundController::class, 'process'])->name('refunds.process');
    Route::post('/refunds/{refund}/cancel', [\App\Http\Controllers\RefundController::class, 'cancel'])->name('refunds.cancel');

    // Credit Notes
    Route::get('/credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');
    Route::get('/credit-notes/create', [CreditNoteController::class, 'create'])->name('credit-notes.create');
    Route::post('/credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');
    Route::get('/credit-notes/{creditNote}', [CreditNoteController::class, 'show'])->name('credit-notes.show');
    Route::post('/credit-notes/{creditNote}/approve', [CreditNoteController::class, 'approve'])->name('credit-notes.approve');
    Route::post('/credit-notes/{creditNote}/apply', [CreditNoteController::class, 'apply'])->name('credit-notes.apply');
    Route::post('/credit-notes/{creditNote}/void', [CreditNoteController::class, 'void'])->name('credit-notes.void');
    Route::get('/credit-notes/{creditNote}/download', [CreditNoteController::class, 'downloadPdf'])->name('credit-notes.download');
    Route::get('/tenants/{tenant}/credit-notes', [CreditNoteController::class, 'forTenant'])->name('tenants.credit-notes');

    // Bank Reconciliation
    Route::get('/reconciliation', [\App\Http\Controllers\ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::post('/reconciliation/{item}/match', [\App\Http\Controllers\ReconciliationController::class, 'match'])->name('reconciliation.match');
    Route::post('/reconciliation/{item}/retry', [\App\Http\Controllers\ReconciliationController::class, 'retry'])->name('reconciliation.retry');
    Route::delete('/reconciliation/{item}', [\App\Http\Controllers\ReconciliationController::class, 'destroy'])->name('reconciliation.destroy');
    Route::post('/reconciliation/import', [\App\Http\Controllers\ReconciliationController::class, 'import'])->name('reconciliation.import');
    Route::post('/reconciliation/process-queue', [\App\Http\Controllers\ReconciliationController::class, 'processQueue'])->name('reconciliation.process-queue');

    // 9. Settings (Integrations & Configuration)
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/business-profile', [\App\Http\Controllers\SettingsController::class, 'updateBusinessProfile'])->name('settings.business.update');
    Route::post('/settings/payment-methods', [\App\Http\Controllers\SettingsController::class, 'updatePaymentMethods'])->name('settings.payment.update');
    Route::post('/settings/notifications', [\App\Http\Controllers\SettingsController::class, 'updateNotificationDefaults'])->name('settings.notifications.update');
    Route::post('/settings/ocr', [\App\Http\Controllers\SettingsController::class, 'updateOcr'])->name('settings.ocr.update');
    Route::post('/settings/ocr/test', [\App\Http\Controllers\SettingsController::class, 'testOcr'])->name('settings.ocr.test');
    Route::post('/settings/branding', [\App\Http\Controllers\SettingsController::class, 'updateBranding'])->name('settings.branding.update');
    Route::post('/settings/branding/logo', [\App\Http\Controllers\SettingsController::class, 'uploadLogo'])->name('settings.branding.logo');
    Route::delete('/settings/branding/logo', [\App\Http\Controllers\SettingsController::class, 'deleteLogo'])->name('settings.branding.logo.delete');
    Route::post('/settings/api-key/delete', [\App\Http\Controllers\SettingsController::class, 'deleteApiKey'])->name('settings.apiKey.delete');

    // 9b. KYC Requirements Management
    Route::get('/settings/kyc-requirements', [\App\Http\Controllers\KycRequirementController::class, 'index'])->name('settings.kyc.index');
    Route::post('/kyc-requirements', [\App\Http\Controllers\KycRequirementController::class, 'store'])->name('kyc-requirements.store');
    Route::put('/kyc-requirements/{kycRequirement}', [\App\Http\Controllers\KycRequirementController::class, 'update'])->name('kyc-requirements.update');
    Route::delete('/kyc-requirements/{kycRequirement}', [\App\Http\Controllers\KycRequirementController::class, 'destroy'])->name('kyc-requirements.destroy');

    // 10. Data Import
    Route::get('/imports', [\App\Http\Controllers\ImportsController::class, 'index'])->name('imports.index');
    Route::post('/imports/upload', [\App\Http\Controllers\ImportsController::class, 'upload'])
        ->middleware('throttle:file-upload')
        ->name('imports.upload');
    Route::get('/imports/template', [\App\Http\Controllers\ImportsController::class, 'downloadTemplate'])->name('imports.template');
    Route::get('/imports/{import}', [\App\Http\Controllers\ImportsController::class, 'show'])->name('imports.show');
    Route::delete('/imports/{import}', [\App\Http\Controllers\ImportsController::class, 'destroy'])->name('imports.destroy');
    Route::post('/imports/{import}/reprocess', [\App\Http\Controllers\ImportsController::class, 'reprocess'])->name('imports.reprocess');

    // 11. Reports & Analytics (redirects to Finance Hub)
    Route::get('/reports', fn () => redirect()->route('finances.reports'))->name('reports.index');
    Route::get('/reports/export/pdf', fn () => redirect()->route('finances.reports.export', ['format' => 'pdf']));
    Route::get('/reports/export/excel', fn () => redirect()->route('finances.reports.export', ['format' => 'xlsx']));
    Route::get('/reports/metrics', fn () => redirect()->route('finances.reports'));

    // Phase-27 BI-COHORT-1/2/3: tenant retention + acquisition + LTV.
    // role:landlord — same scope as finances.reports; tenant-facing
    // analytics surface is a separate Phase 27 finding (BI-BUILDER).
    Route::middleware('role:landlord')
        ->get('/reports/cohort', [\App\Http\Controllers\Reports\CohortController::class, 'index'])
        ->name('reports.cohort');

    // Phase-27 BI-NOI-1/2/3: NOI per property + cap rate + expense allocation.
    Route::middleware('role:landlord')
        ->get('/reports/noi', [\App\Http\Controllers\Reports\NoiController::class, 'index'])
        ->name('reports.noi');

    // Phase-27 BI-FORECAST-1/2/3: rent-roll forecast + seasonality + vacancy.
    Route::middleware('role:landlord')
        ->get('/reports/forecast', [\App\Http\Controllers\Reports\ForecastController::class, 'index'])
        ->name('reports.forecast');

    // Phase-27 BI-BUILDER-1/2/3: saved-report library + drag-drop builder.
    // The SAFE SQL generator (ReportBuilderService) is the security-critical
    // path here — see Phase27BuilderInjectionTest.
    Route::middleware('role:landlord')->prefix('reports/builder')->name('reports.builder.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Reports\BuilderController::class, 'index'])->name('index');
        Route::post('/run', [\App\Http\Controllers\Reports\BuilderController::class, 'run'])->name('run');
        Route::post('/', [\App\Http\Controllers\Reports\BuilderController::class, 'store'])->name('store');
        Route::delete('/{report}', [\App\Http\Controllers\Reports\BuilderController::class, 'destroy'])->name('destroy');
        // Phase-50 DRILL-DOWN-3: navigate from a parent report row to the
        // filtered child synthesised by DrillDownService.
        Route::get('/{report}/drill', [\App\Http\Controllers\Reports\BuilderController::class, 'drill'])->name('drill');
    });

    // Phase-50 TEMPLATE-MARKETPLACE-3: platform-curated report templates
    // gallery + one-click clone into a per-landlord SavedReport.
    Route::middleware('role:landlord')->prefix('reports/templates')->name('reports.templates.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Reports\ReportTemplateController::class, 'index'])->name('index');
        Route::post('/{template}/clone', [\App\Http\Controllers\Reports\ReportTemplateController::class, 'clone'])->name('clone');
    });

    // Phase-50 CUSTOM-METRICS-3: landlord-defined formulas evaluated by
    // MetricFormulaService and surfaced as derived columns in the builder.
    Route::middleware('role:landlord')->prefix('reports/metrics')->name('reports.metrics.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Reports\ReportMetricController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Reports\ReportMetricController::class, 'store'])->name('store');
        Route::delete('/{metric}', [\App\Http\Controllers\Reports\ReportMetricController::class, 'destroy'])->name('destroy');
    });

    // Phase-50 LANDLORD-DASHBOARDS-3: composable dashboard show route.
    // Slug is per-landlord — the controller scopes by (landlord_id, slug).
    Route::middleware('role:landlord')
        ->get('/dashboards/{slug}', [\App\Http\Controllers\Reports\DashboardController::class, 'show'])
        ->name('dashboards.show');

    // Phase-55 WIDGET-ORDERING-1: persist landlord widget order via the
    // Phase-50 landlord_dashboards.layout JSON column (slug='main_dashboard').
    Route::middleware('role:landlord')
        ->patch('/dashboards/preferences', [\App\Http\Controllers\DashboardPreferenceController::class, 'update'])
        ->name('dashboards.preferences.update');

    // Phase-54 COST-UI-2: landlord-only manual cost entry. parts category
    // auto-recorded via Phase 49 TicketResolutionService::recordParts; this
    // endpoint accepts vendor|labor|other only (validator enforces).
    Route::middleware('role:landlord')
        ->post('/tickets/{ticket}/costs', [\App\Http\Controllers\TicketCostController::class, 'store'])
        ->name('tickets.costs.store');

    // Phase-54 PARTS-REORDER-3: landlord-facing surface over the
    // draft_purchase_orders the parts:reorder-suggest cron materialises.
    Route::middleware('role:landlord')->prefix('parts/purchase-orders')->name('parts.purchase-orders.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PartsPurchaseOrderController::class, 'index'])->name('index');
        Route::post('/{order}/confirm', [\App\Http\Controllers\PartsPurchaseOrderController::class, 'confirm'])->name('confirm');
        Route::post('/{order}/cancel', [\App\Http\Controllers\PartsPurchaseOrderController::class, 'cancel'])->name('cancel');
    });

    // Phase-54 SLA-LANDLORD-UI-1: landlord-scoped CRUD over SLA overrides.
    // NOT under /admin — that namespace is super-admin only. Landlord
    // overrides + global defaults coexist via the Phase-49 cascade in
    // SlaDefinitionService::resolveFor.
    Route::middleware('role:landlord')->prefix('sla')->name('sla.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SlaDefinitionController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\SlaDefinitionController::class, 'store'])->name('store');
        Route::patch('/{sla}', [\App\Http\Controllers\SlaDefinitionController::class, 'update'])->name('update');
        Route::delete('/{sla}', [\App\Http\Controllers\SlaDefinitionController::class, 'destroy'])->name('destroy');
    });

    // Phase-27 BI-DELIVERY-2/3: scheduled report delivery self-serve.
    Route::middleware('role:landlord')->prefix('reports/scheduled')->name('reports.scheduled.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Reports\ScheduledController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Reports\ScheduledController::class, 'store'])->name('store');
        Route::delete('/{schedule}', [\App\Http\Controllers\Reports\ScheduledController::class, 'destroy'])->name('destroy');
        // Phase-50 REAL-TIME-PREVIEW-2: same payload the next send would
        // carry; cross-tenant saved_report_id 403s at the controller.
        Route::post('/preview', [\App\Http\Controllers\Reports\ScheduledController::class, 'preview'])->name('preview');
    });

    // 12. Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationsController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/overview', [\App\Http\Controllers\NotificationsController::class, 'overview'])->name('notifications.overview');
    // RATE-3: bulk-notify limiter caps fan-out spam vectors. 2/min and
    // 20/hour per landlord; per-row cap separately enforced in
    // SendBulkNotificationRequest.
    Route::post('/notifications/send', [\App\Http\Controllers\NotificationsController::class, 'send'])
        ->middleware('throttle:bulk-notify')
        ->name('notifications.send');
    Route::post('/notifications/send-bulk', [\App\Http\Controllers\NotificationsController::class, 'sendBulk'])
        ->middleware('throttle:bulk-notify')
        ->name('notifications.sendBulk');
    Route::post('/notifications/rent-reminders', [\App\Http\Controllers\NotificationsController::class, 'sendRentReminders'])
        ->middleware('throttle:bulk-notify')
        ->name('notifications.sendRentReminders');
    Route::post('/notifications/arrears-notices', [\App\Http\Controllers\NotificationsController::class, 'sendArrearsNotices'])
        ->middleware('throttle:bulk-notify')
        ->name('notifications.sendArrearsNotices');
    Route::get('/notifications/preferences', [\App\Http\Controllers\NotificationsController::class, 'getPreferences'])->name('notifications.preferences');
    Route::post('/notifications/preferences', [\App\Http\Controllers\NotificationsController::class, 'updatePreferences'])->name('notifications.updatePreferences');
    Route::post('/notifications/{notification}/mark-read', [\App\Http\Controllers\NotificationsController::class, 'markAsRead'])->name('notifications.markAsRead');
    // RATE-11: retry hits the SMS/email provider — bound to 'sensitive'.
    Route::post('/notifications/{notification}/retry', [\App\Http\Controllers\NotificationsController::class, 'retry'])
        ->middleware('throttle:sensitive')
        ->name('notifications.retry');
    Route::delete('/notifications/{notification}', [\App\Http\Controllers\NotificationsController::class, 'destroy'])->name('notifications.destroy');

    // Notification Templates
    Route::get('/notifications/templates', [\App\Http\Controllers\NotificationsController::class, 'templates'])->name('notifications.templates');
    Route::post('/notifications/templates', [\App\Http\Controllers\NotificationsController::class, 'storeTemplate'])->name('notifications.templates.store');
    Route::put('/notifications/templates/{template}', [\App\Http\Controllers\NotificationsController::class, 'updateTemplate'])->name('notifications.templates.update');
    Route::delete('/notifications/templates/{template}', [\App\Http\Controllers\NotificationsController::class, 'destroyTemplate'])->name('notifications.templates.destroy');
    Route::post('/notifications/templates/{template}/preview', [\App\Http\Controllers\NotificationsController::class, 'previewTemplate'])
        ->middleware('throttle:provider-test')
        ->name('notifications.templates.preview');

    // Notification Schedules
    Route::get('/notifications/schedules', [\App\Http\Controllers\NotificationsController::class, 'schedules'])->name('notifications.schedules');
    Route::post('/notifications/schedules', [\App\Http\Controllers\NotificationsController::class, 'storeSchedule'])->name('notifications.schedules.store');
    Route::put('/notifications/schedules/{schedule}', [\App\Http\Controllers\NotificationsController::class, 'updateSchedule'])->name('notifications.schedules.update');
    Route::delete('/notifications/schedules/{schedule}', [\App\Http\Controllers\NotificationsController::class, 'destroySchedule'])->name('notifications.schedules.destroy');
    Route::post('/notifications/schedules/{schedule}/toggle', [\App\Http\Controllers\NotificationsController::class, 'toggleSchedule'])->name('notifications.schedules.toggle');
    // RATE-11: run-now triggers an immediate broadcast — sensitive bound.
    Route::post('/notifications/schedules/{schedule}/run', [\App\Http\Controllers\NotificationsController::class, 'runScheduleNow'])
        ->middleware('throttle:sensitive')
        ->name('notifications.schedules.run');

    // Notification Settings
    Route::get('/notifications/settings', [\App\Http\Controllers\NotificationsController::class, 'settings'])->name('notifications.settings');
    Route::post('/notifications/settings/provider/{provider}', [\App\Http\Controllers\NotificationsController::class, 'updateProviderSettings'])->name('notifications.settings.provider');
    Route::post('/notifications/settings/test/{provider}', [\App\Http\Controllers\NotificationsController::class, 'testProvider'])
        ->middleware('throttle:provider-test')
        ->name('notifications.settings.test');
    Route::post('/notifications/settings/complete-setup', [\App\Http\Controllers\NotificationsController::class, 'completeSetup'])->name('notifications.settings.complete-setup');
    Route::post('/notifications/push/generate-keys', [\App\Http\Controllers\NotificationsController::class, 'generateVapidKeys'])->name('notifications.push.generate-keys');
    Route::get('/notifications/settings/status', [\App\Http\Controllers\NotificationsController::class, 'checkSetupStatus'])->name('notifications.settings.status');
    Route::post('/notifications/settings/vapid', [\App\Http\Controllers\NotificationsController::class, 'generateVapidKeys'])->name('notifications.settings.vapid');
    Route::get('/notifications/settings/global', [\App\Http\Controllers\NotificationsController::class, 'getGlobalPreferences'])->name('notifications.settings.global.get');
    Route::post('/notifications/settings/global', [\App\Http\Controllers\NotificationsController::class, 'updateGlobalPreferences'])->name('notifications.settings.global');
    Route::post('/notifications/settings/whatsapp-templates', [\App\Http\Controllers\NotificationsController::class, 'updateWhatsAppTemplates'])->name('notifications.settings.whatsapp-templates');

    // Push Notifications
    Route::post('/notifications/push/subscribe', [\App\Http\Controllers\NotificationsController::class, 'subscribePush'])->name('notifications.push.subscribe');
    Route::post('/notifications/push/unsubscribe', [\App\Http\Controllers\NotificationsController::class, 'unsubscribePush'])->name('notifications.push.unsubscribe');
    Route::get('/notifications/push/key', [\App\Http\Controllers\NotificationsController::class, 'getVapidPublicKey'])->name('notifications.push.key');

    // 13. Bulk Operations
    Route::get('/bulk-operations', [\App\Http\Controllers\BulkOperationsController::class, 'index'])->name('bulk.index');
    // RATE-4: bulk-ops limiter (3/min/user) + per-controller Cache::lock
    // serialization in BulkOperationsController so concurrent calls per
    // landlord don't race.
    Route::post('/bulk-operations/adjust-rent', [\App\Http\Controllers\BulkOperationsController::class, 'adjustRent'])
        ->middleware('throttle:bulk-ops')
        ->name('bulk.adjustRent');
    Route::post('/bulk-operations/update-unit-status', [\App\Http\Controllers\BulkOperationsController::class, 'updateUnitStatus'])
        ->middleware('throttle:bulk-ops')
        ->name('bulk.updateUnitStatus');
    Route::post('/bulk-operations/terminate-leases', [\App\Http\Controllers\BulkOperationsController::class, 'terminateLeases'])
        ->middleware('throttle:bulk-ops')
        ->name('bulk.terminateLeases');
    Route::post('/bulk-operations/extend-leases', [\App\Http\Controllers\BulkOperationsController::class, 'extendLeases'])
        ->middleware('throttle:bulk-ops')
        ->name('bulk.extendLeases');
    Route::post('/bulk-operations/adjust-deposits', [\App\Http\Controllers\BulkOperationsController::class, 'adjustDeposits'])
        ->middleware('throttle:bulk-ops')
        ->name('bulk.adjustDeposits');
    Route::post('/bulk-operations/update-target-rent', [\App\Http\Controllers\BulkOperationsController::class, 'updateTargetRent'])
        ->middleware('throttle:bulk-ops')
        ->name('bulk.updateTargetRent');
    Route::post('/bulk-operations/update-meter-numbers', [\App\Http\Controllers\BulkOperationsController::class, 'updateMeterNumbers'])
        ->middleware('throttle:bulk-ops')
        ->name('bulk.updateMeterNumbers');

    // 14. Tickets (Issues & Complaints)
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])
        ->middleware('throttle:file-upload')
        ->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::put('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::post('/tickets/{ticket}/assign-vendor', [\App\Http\Controllers\TicketVendorAssignmentController::class, 'store'])
        ->middleware('role:landlord')
        ->name('tickets.assign-vendor');
    Route::post('/tickets/{ticket}/comment', [TicketController::class, 'addComment'])->name('tickets.comment');
    Route::post('/tickets/{ticket}/resolve', [TicketController::class, 'resolve'])->name('tickets.resolve');
    Route::post('/tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');
    Route::post('/tickets/{ticket}/feedback', [TicketController::class, 'submitFeedback'])->name('tickets.feedback');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
    // Phase-45 TICKET-PHOTOS-1/2: annotated copy of a photo attachment.
    Route::post('/tickets/{ticket}/attachments/{document}/annotation', [\App\Http\Controllers\TicketAnnotationController::class, 'store'])
        ->name('tickets.attachments.annotation');
    Route::get('/buildings/{building}/units', [TicketController::class, 'getUnits'])->name('buildings.units');

    // 14b. Complaints (Alias to Tickets with category filter)
    Route::get('/complaints', [TicketController::class, 'index'])->name('complaints.index')->defaults('category', 'complaint');

    // 15. Payments (Legacy route - redirect to Payments Hub)
    Route::get('/payments', fn () => redirect()->route('payments-hub.transactions'))->name('payments.index');

    // 15b. Payments Hub (Unified Payment Management)
    Route::prefix('payments-hub')->name('payments-hub.')->group(function () {
        // Tab Routes
        Route::get('/', [PaymentsHubController::class, 'index'])->name('index');
        Route::get('/overview', [PaymentsHubController::class, 'overview'])->name('overview');
        Route::get('/collection', [PaymentsHubController::class, 'collection'])->name('collection');
        Route::get('/transactions', [PaymentsHubController::class, 'transactions'])->name('transactions');
        Route::get('/analytics', [PaymentsHubController::class, 'analytics'])->name('analytics');
        Route::get('/settings', [PaymentsHubController::class, 'settings'])->name('settings');

        // Collection Tab Actions
        Route::post('/payment-methods', [PaymentsHubController::class, 'updatePaymentMethods'])->name('payment-methods.update');
        Route::post('/payout-accounts', [PaymentsHubController::class, 'storePayoutAccount'])->name('payout.store');
        Route::post('/payout-accounts/{account}/primary', [PaymentsHubController::class, 'setPayoutPrimary'])->name('payout.primary');
        Route::post('/payout-accounts/{account}/sync', [PaymentsHubController::class, 'syncPayoutAccount'])->name('payout.sync');
        Route::delete('/payout-accounts/{account}', [PaymentsHubController::class, 'destroyPayoutAccount'])->name('payout.destroy');

        // Settings Tab Actions
        Route::post('/preferences', [PaymentsHubController::class, 'updatePreferences'])->name('preferences.update');

        // Setup Wizard
        Route::post('/complete-setup', [PaymentsHubController::class, 'completeSetup'])->name('complete-setup');

        // AJAX APIs
        // RATE-10: bank-verify limiter is tighter than the general api
        // throttle since both endpoints round-trip to Paystack and the
        // verify-account response leaks holder names.
        Route::get('/banks', [PaymentsHubController::class, 'getBanks'])
            ->middleware('throttle:bank-verify')
            ->name('banks');
        Route::post('/verify-account', [PaymentsHubController::class, 'verifyAccount'])
            ->middleware('throttle:bank-verify')
            ->name('verify-account');
    });

    // 15c. Finances Hub (Unified Finance Management - New Architecture)
    Route::prefix('finances')->name('finances.')->group(function () {
        Route::get('/', [FinancesController::class, 'index'])->name('index');
        Route::get('/overview', [FinancesController::class, 'overview'])->name('overview');
        Route::get('/invoices', [FinancesController::class, 'invoices'])->name('invoices');
        Route::get('/payments', [FinancesController::class, 'payments'])->name('payments');
        Route::get('/payments/record', [PaymentController::class, 'create'])->name('payments.record');
        Route::post('/payments/record', [PaymentController::class, 'storeManual'])->name('payments.store-manual');
        Route::get('/payments/bulk-import', [PaymentController::class, 'bulkImportForm'])->name('payments.bulk-import');
        Route::post('/payments/bulk-import/validate', [PaymentController::class, 'validateBulkImport'])->name('payments.bulk-import.validate');
        Route::post('/payments/bulk-import/process', [PaymentController::class, 'processBulkImport'])->name('payments.bulk-import.process');
        Route::get('/payments/bulk-import/template', [PaymentController::class, 'downloadBulkTemplate'])->name('payments.bulk-import.template');
        Route::get('/refunds', [FinancesController::class, 'refunds'])->name('refunds');
        Route::get('/refunds/create', [RefundController::class, 'createStandalone'])->name('refunds.create');
        Route::post('/refunds/store', [RefundController::class, 'storeStandalone'])->name('refunds.store');
        Route::get('/reconciliation', [FinancesController::class, 'reconciliation'])->name('reconciliation');
        Route::get('/deposits', [FinanceDepositController::class, 'index'])->name('deposits');
        Route::get('/arrears', [FinancesController::class, 'arrears'])->name('arrears');
        Route::get('/settings', [FinanceSettingsController::class, 'index'])->name('settings');
        Route::post('/settings/payment-methods', [FinanceSettingsController::class, 'updatePaymentMethods'])->name('settings.payment-methods');
        Route::post('/settings/invoice', [FinanceSettingsController::class, 'updateInvoiceSettings'])->name('settings.invoice');
        Route::post('/settings/reminder', [FinanceSettingsController::class, 'updateReminderSettings'])->name('settings.reminder');
        Route::post('/settings/receipt', [FinanceSettingsController::class, 'updateReceiptSettings'])->name('settings.receipt');
        Route::get('/settings/receipt/preview', [FinanceSettingsController::class, 'previewReceipt'])->name('settings.receipt.preview');
        Route::post('/settings/default-currency', [FinanceSettingsController::class, 'updateDefaultCurrency'])->name('settings.default-currency');
        Route::post('/settings/fiscal-year', [FinanceSettingsController::class, 'updateFiscalYearSettings'])->name('settings.fiscal-year');

        // Reports
        Route::get('/reports', [FinanceReportController::class, 'index'])->name('reports');
        Route::get('/reports/export', [FinanceReportController::class, 'export'])
            ->middleware('throttle:export')
            ->name('reports.export');

        // Phase-30 INT-ACCT-EXPORT-1/3: accountant-facing QuickBooks IIF + Sage CSV export
        Route::get('/accounting/export', [\App\Http\Controllers\Finance\AccountingExportController::class, 'index'])
            ->name('accounting.export.index');
        Route::get('/accounting/export/download', [\App\Http\Controllers\Finance\AccountingExportController::class, 'export'])
            ->middleware('throttle:export')
            ->name('accounting.export.download');

        // Phase-30 INT-PERIOD-LOCK-1/3: accounting period management
        Route::get('/periods', [\App\Http\Controllers\Finance\AccountingPeriodController::class, 'index'])
            ->name('periods.index');
        Route::post('/periods/close', [\App\Http\Controllers\Finance\AccountingPeriodController::class, 'close'])
            ->name('periods.close');
        Route::post('/periods/{period}/reopen', [\App\Http\Controllers\Finance\AccountingPeriodController::class, 'reopen'])
            ->name('periods.reopen');

        // Templates
        Route::get('/templates', [FinanceTemplateController::class, 'index'])->name('templates');
        Route::get('/templates/invoices', [FinanceTemplateController::class, 'invoices'])->name('templates.invoices');
        Route::get('/templates/receipts', [FinanceTemplateController::class, 'receipts'])->name('templates.receipts');
        Route::get('/templates/credit-notes', [FinanceTemplateController::class, 'creditNotes'])->name('templates.credit-notes');

        // Late Fees Management
        Route::get('/late-fees', [LateFeeController::class, 'index'])->name('late-fees');
        Route::post('/late-fee-policies', [LateFeeController::class, 'store'])->name('late-fee-policies.store');
        Route::put('/late-fee-policies/{policy}', [LateFeeController::class, 'update'])->name('late-fee-policies.update');
        Route::delete('/late-fee-policies/{policy}', [LateFeeController::class, 'destroy'])->name('late-fee-policies.destroy');
        Route::post('/late-fee-policies/{policy}/toggle', [LateFeeController::class, 'toggle'])->name('late-fee-policies.toggle');
        Route::post('/late-fees/{lateFee}/waive', [LateFeeController::class, 'waive'])->name('late-fees.waive');
        Route::post('/invoices/{invoice}/waive-all-late-fees', [LateFeeController::class, 'waiveAll'])->name('invoices.waive-all-late-fees');
        Route::get('/invoices/{invoice}/late-fees', [LateFeeController::class, 'invoiceLateFees'])->name('invoices.late-fees');

        // Notifications
        Route::post('/notifications/arrears', [FinanceNotificationController::class, 'sendArrearsNotices'])->name('notifications.arrears');
        Route::post('/notifications/reminders', [FinanceNotificationController::class, 'sendRentReminders'])->name('notifications.reminders');

        // Reconciliation
        Route::post('/reconciliation/import', [FinancesController::class, 'importBankStatement'])->name('reconciliation.import');
        Route::post('/reconciliation/process-queue', [FinancesController::class, 'processReconciliationQueue'])->name('reconciliation.process-queue');

        // API endpoints for modals (return JSON)
        Route::get('/invoices/{invoice}/detail', [FinancesController::class, 'invoiceDetail'])->name('invoices.detail');
        Route::get('/payments/{payment}/detail', [FinancesController::class, 'paymentDetail'])->name('payments.detail');
        Route::post('/payments/{payment}/match', [FinancesController::class, 'matchPayment'])->name('payments.match');

        // Deposit actions
        Route::post('/deposits/{lease}/refund', [FinanceDepositController::class, 'refund'])->name('deposits.refund');
        Route::post('/deposits/{lease}/forfeit', [FinanceDepositController::class, 'forfeit'])->name('deposits.forfeit');
        Route::get('/deposits/{lease}/transactions', [FinanceDepositController::class, 'transactions'])->name('deposits.transactions');

        // Export endpoints (rate limited to prevent abuse of resource-intensive operations)
        Route::get('/deposits/export', [FinanceDepositController::class, 'export'])
            ->middleware('throttle:export')
            ->name('deposits.export');
        Route::get('/invoices/export', [FinancesController::class, 'exportInvoices'])
            ->middleware('throttle:export')
            ->name('invoices.export');
        Route::get('/payments/export', [FinancesController::class, 'exportPayments'])
            ->middleware('throttle:export')
            ->name('payments.export');
        Route::get('/expenses/export', [ExpenseController::class, 'export'])
            ->middleware('throttle:export')
            ->name('expenses.export');
        Route::get('/vendors/export', [ExpenseController::class, 'exportVendors'])
            ->middleware('throttle:export')
            ->name('vendors.export');

        // Expenses Management
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::get('/expenses/{expense}/detail', [ExpenseController::class, 'show'])->name('expenses.detail');

        // Expense Categories
        Route::post('/expense-categories', [ExpenseController::class, 'storeCategory'])->name('expense-categories.store');
        Route::put('/expense-categories/{category}', [ExpenseController::class, 'updateCategory'])->name('expense-categories.update');
        Route::delete('/expense-categories/{category}', [ExpenseController::class, 'destroyCategory'])->name('expense-categories.destroy');

        // Vendors
        Route::post('/vendors', [ExpenseController::class, 'storeVendor'])->name('vendors.store');
        Route::put('/vendors/{vendor}', [ExpenseController::class, 'updateVendor'])->name('vendors.update');
        Route::delete('/vendors/{vendor}', [ExpenseController::class, 'destroyVendor'])->name('vendors.destroy');
    });

    // 16. Deposits (Security Deposits Tracking)
    Route::get('/deposits', [DepositController::class, 'index'])->name('deposits.index');

    // 17. Arrears (Overdue Tracking)
    Route::get('/arrears', [ArrearsController::class, 'index'])->name('arrears.index');

    // 18. Water Settings (Global Water Billing Configuration)
    Route::get('/water/settings', [WaterSettingsController::class, 'index'])->name('water.settings');
    Route::put('/water/settings', [WaterSettingsController::class, 'update'])->name('water.settings.update');

    // 19. Lease Agreements (Archive)
    Route::get('/lease-agreements', [LeaseController::class, 'index'])->name('leases.index');

    // 20. Tenant History (Past Tenants)
    Route::get('/tenants/history', [TenantController::class, 'history'])->name('tenants.history');

    // 21. Activity Logs (Audit Trail)
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    // 21b. Audit Logs (Comprehensive Audit System)
    Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('/export', [AuditLogController::class, 'export'])
            ->middleware('throttle:export')
            ->name('export');
        Route::get('/for-model', [AuditLogController::class, 'forModel'])->name('forModel');
        Route::get('/{auditLog}', [AuditLogController::class, 'show'])->name('show');
    });

    // 22. User Profile (Default Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/verification', [ProfileController::class, 'updateVerification'])->name('profile.verification.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->middleware('throttle:sensitive')
        ->name('profile.destroy');

    // Phase-24 I18N-INFRA-4: per-user locale switch. Persists to
    // users.locale + the session; the SetLocale middleware applies it
    // on the next request.
    Route::patch('/locale', [\App\Http\Controllers\LocaleController::class, 'update'])->name('locale.update');

    // 16. Help Center (All Users)
    // Phase-22 PERF-CACHE-2: help pages are global reference content
    // (not per-landlord) — safe to carry ETag + private cache headers
    // via the cache.read middleware. The allow-list lives in
    // config/observability.php read_cache.routes.
    Route::get('/help', [HelpController::class, 'index'])
        ->middleware('cache.read')
        ->name('help.index');
    Route::get('/help/search', [HelpController::class, 'search'])
        ->middleware('throttle:search')
        ->name('help.search');
    Route::get('/help/{article:slug}', [HelpController::class, 'show'])
        ->middleware('cache.read')
        ->name('help.show');

    // Phase-25 API-AUTH-1: API token self-serve for landlords
    // (mint/list/revoke Sanctum PATs for integrations like
    // QuickBooks Sync, Zapier, custom landlord ERPs). Super-admins
    // pass the role gate via EnsureRole's bypass.
    Route::middleware('role:landlord')->prefix('settings/api-tokens')->name('settings.api-tokens.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ApiTokenController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\ApiTokenController::class, 'store'])->name('store');
        Route::delete('/{token}', [\App\Http\Controllers\ApiTokenController::class, 'destroy'])->name('destroy');
    });

    // Phase-25 API-WEBHOOK-1/2/3: outbound webhook subscriptions.
    // Landlords register integration endpoints to receive
    // payment.received / invoice.created / lease.signed events.
    Route::middleware('role:landlord')->prefix('settings/webhooks')->name('settings.webhooks.')->group(function () {
        Route::get('/', [\App\Http\Controllers\WebhookSubscriptionController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\WebhookSubscriptionController::class, 'store'])->name('store');
        Route::get('/{subscription}', [\App\Http\Controllers\WebhookSubscriptionController::class, 'show'])->name('show');
        Route::patch('/{subscription}', [\App\Http\Controllers\WebhookSubscriptionController::class, 'update'])->name('update');
        Route::delete('/{subscription}', [\App\Http\Controllers\WebhookSubscriptionController::class, 'destroy'])->name('destroy');
        Route::post('/{subscription}/test', [\App\Http\Controllers\WebhookSubscriptionController::class, 'test'])->name('test');
        Route::post('/deliveries/{delivery}/retry', [\App\Http\Controllers\WebhookSubscriptionController::class, 'retry'])->name('deliveries.retry');
    });

    // 17. Subscription Management (Landlords Only)
    Route::middleware('role:landlord')->prefix('subscription')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index'])->name('subscription.index');
        Route::get('/plans', [SubscriptionController::class, 'plans'])->name('subscription.plans');
        Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
        // Phase-60 PLAN-CHANGE-2: self-serve upgrade/downgrade.
        Route::post('/change', [SubscriptionController::class, 'change'])->name('subscription.change');
        // Phase-60 COUPONS-3: coupon redemption on the plans page.
        Route::post('/apply-coupon', [SubscriptionController::class, 'applyCoupon'])->name('subscription.apply-coupon');
        // Phase-60 BILLING-PORTAL-2: Stripe Customer Portal redirect.
        Route::post('/billing/portal', [SubscriptionController::class, 'portal'])->name('subscription.billing.portal');
        Route::get('/callback', [SubscriptionController::class, 'callback'])->name('subscription.callback');
        Route::post('/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
        Route::post('/resume', [SubscriptionController::class, 'resume'])->name('subscription.resume');
        Route::get('/payments/{payment}/invoice', [SubscriptionController::class, 'downloadInvoice'])->name('subscription.invoice');
    });

    // 18. Payout Accounts Management (Landlords Only) - Legacy routes, redirect to Payments Hub
    Route::middleware('role:landlord')->prefix('settings/payout')->name('settings.payout.')->group(function () {
        Route::get('/', fn () => redirect()->route('payments-hub.collection'))->name('index');
        // Keep action routes pointing to PaymentsHubController for backward compatibility
        Route::post('/', [PaymentsHubController::class, 'storePayoutAccount'])->name('store');
        Route::post('/{account}/primary', [PaymentsHubController::class, 'setPayoutPrimary'])->name('primary');
        Route::post('/{account}/sync', [PaymentsHubController::class, 'syncPayoutAccount'])->name('sync');
        Route::delete('/{account}', [PaymentsHubController::class, 'destroyPayoutAccount'])->name('destroy');
    });

    // API endpoints for payout accounts - redirect to Payments Hub endpoints
    Route::middleware('role:landlord')->group(function () {
        // RATE-10: bank-verify on the legacy alias too.
        Route::get('/api/banks', [PaymentsHubController::class, 'getBanks'])
            ->middleware('throttle:bank-verify')
            ->name('api.banks');
        Route::post('/api/verify-account', [PaymentsHubController::class, 'verifyAccount'])
            ->middleware('throttle:bank-verify')
            ->name('api.verify-account');
    });

    // 19. KYC Review Routes (Landlords and Caretakers)
    Route::middleware('role:landlord,caretaker')->group(function () {
        Route::get('/kyc/pending', [\App\Http\Controllers\TenantKycController::class, 'pendingReviews'])->name('kyc.pending');
        Route::post('/kyc/submissions/{submission}/review', [\App\Http\Controllers\TenantKycController::class, 'review'])->name('kyc.review');
    });
});

// --- SUPER ADMIN ROUTES ---
Route::middleware(['auth', 'verified', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::get('/landlords', [AdminController::class, 'landlords'])->name('admin.landlords');
    Route::get('/landlords/{user}', [AdminController::class, 'showLandlord'])->name('admin.landlords.show');
    Route::post('/landlords', [AdminController::class, 'createLandlord'])->name('admin.landlords.store');
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::post('/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus'])->name('admin.users.toggleStatus');
    Route::post('/impersonate/{user}', [AdminController::class, 'impersonate'])
        ->middleware('throttle:sensitive')
        ->name('admin.impersonate');

    // System Settings
    Route::get('/settings', [AdminController::class, 'settings'])->name('admin.settings');

    // Payment Gateway Settings
    Route::post('/settings/payment', [AdminController::class, 'updatePaymentSettings'])->name('admin.settings.payment');
    Route::post('/settings/payment/test', [AdminController::class, 'testPaystackConnection'])->name('admin.settings.payment.test');

    // Note: Email and SMS settings have been consolidated to the Notification Center
    // See: Operations > Notifications > Settings (routes: notifications.settings.*)

    // Platform Billing Management
    Route::prefix('billing')->name('admin.billing.')->group(function () {
        Route::get('/', [AdminBillingController::class, 'index'])->name('index');
        Route::post('/model', [AdminBillingController::class, 'switchModel'])->name('model');
        Route::post('/fees', [AdminBillingController::class, 'updateFees'])->name('fees');
        Route::get('/analytics', [AdminBillingController::class, 'analytics'])->name('analytics');
        Route::get('/history', [AdminBillingController::class, 'history'])->name('history');
        Route::post('/preview-fee', [AdminBillingController::class, 'previewFee'])->name('preview-fee');
    });

    // Phase-40 GATEWAY-PREF-2: super_admin per-landlord gateway switcher.
    Route::get('/gateways', [\App\Http\Controllers\Admin\AdminGatewaysController::class, 'index'])
        ->name('admin.gateways.index');
    Route::post('/gateways/{user}/preference', [\App\Http\Controllers\Admin\AdminGatewaysController::class, 'update'])
        ->name('admin.gateways.update');

    // Phase-42 TAX-2: KRA PIN + VAT-rate-override + Stripe Tax opt-in
    // for a single landlord's PaymentConfiguration.
    Route::post('/gateways/{user}/tax-config', [\App\Http\Controllers\Admin\AdminGatewaysController::class, 'updateTaxConfig'])
        ->name('admin.gateways.tax-config');

    // Phase-42 PLAN-SYNC-AUTO-3: per-plan drift_resolve_mode setter
    // consumed by handlePriceUpdated -> PlanDriftResolver.
    Route::post('/gateways/plan-drift-mode/{plan}', [\App\Http\Controllers\Admin\AdminGatewaysController::class, 'updateDriftResolveMode'])
        ->name('admin.gateways.plan-drift-mode');
});

// Phase-42 CART-2: cart checkout initialization. Auth-gated +
// ownership-checked inside the controller because both tenant and
// landlord may legitimately submit.
Route::middleware(['auth'])->group(function () {
    Route::post('/checkout/sessions/{session}/initialize', [\App\Http\Controllers\CartCheckoutController::class, 'initialize'])
        ->name('checkout.sessions.initialize');
});

// Stop impersonating (available to anyone being impersonated)
Route::post('/admin/stop-impersonating', [AdminController::class, 'stopImpersonating'])
    ->middleware('auth')
    ->name('admin.stopImpersonating');

// --- NOTIFICATIONS API (All authenticated users) ---
Route::middleware('auth')->group(function () {
    Route::get('/notifications/api', [\App\Http\Controllers\TenantNotificationController::class, 'getNotifications'])->name('notifications.api');
    Route::patch('/notifications/{notification}/read', [\App\Http\Controllers\TenantNotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [\App\Http\Controllers\TenantNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});

// --- TENANT PAYMENT VERIFICATION ROUTES (Accessible without payment verification) ---
Route::middleware(['auth', 'role:tenant'])->prefix('tenant')->name('tenant.')->group(function () {
    Route::get('/payment-required', [TenantPaymentVerificationController::class, 'showPaymentRequired'])->name('payment-required');
    Route::post('/payment/submit', [TenantPaymentVerificationController::class, 'submitProofOfPayment'])
        ->middleware('throttle:file-upload')
        ->name('payment.submit');
    Route::post('/payment/pay-online', [TenantPaymentVerificationController::class, 'payOnline'])
        ->middleware('throttle:payment')
        ->name('payment.pay-online');
    Route::get('/payment/callback', [PaymentController::class, 'handleCallback'])->name('payment.callback');
});

// --- TENANT KYC ROUTES (Accessible without KYC completion but requires payment verification) ---
Route::middleware(['auth', 'role:tenant', 'payment.verified'])->prefix('tenant')->name('tenant.')->group(function () {
    Route::get('/complete-profile', [\App\Http\Controllers\TenantKycController::class, 'show'])->name('kyc.show');
    Route::post('/complete-profile', [\App\Http\Controllers\TenantKycController::class, 'update'])->name('kyc.update');
});

// --- TENANT ROUTES (Require Payment Verification + KYC completion) ---
Route::middleware(['auth', 'role:tenant', 'payment.verified', 'kyc.complete'])->prefix('tenant')->group(function () {
    Route::redirect('/payments', '/tenant/finances')->name('tenant.payments');
    Route::get('/lease', [TenantPortalController::class, 'lease'])->name('tenant.lease');

    // Tenant Finances (New Simplified Payment Flow)
    Route::get('/finances', [TenantFinancesController::class, 'index'])->name('tenant.finances.index');
    Route::get('/finances/pay/{invoice}', [TenantFinancesController::class, 'pay'])->name('tenant.finances.pay');
    Route::get('/finances/history', [TenantFinancesController::class, 'history'])->name('tenant.finances.history');

    // Tenant Notifications
    Route::get('/notifications', [\App\Http\Controllers\TenantNotificationController::class, 'index'])->name('tenant.notifications');
    Route::get('/notifications/api', [\App\Http\Controllers\TenantNotificationController::class, 'getNotifications'])->name('tenant.notifications.api');
    Route::patch('/notifications/{notification}/read', [\App\Http\Controllers\TenantNotificationController::class, 'markAsRead'])->name('tenant.notifications.read');
    Route::patch('/notifications/read-all', [\App\Http\Controllers\TenantNotificationController::class, 'markAllAsRead'])->name('tenant.notifications.read-all');

    // Phase-28 TENANT-PROFILE-1/2/3: dedicated tenant profile surface.
    Route::get('/profile', [\App\Http\Controllers\TenantProfileController::class, 'edit'])->name('tenant.profile.edit');
    Route::patch('/profile', [\App\Http\Controllers\TenantProfileController::class, 'update'])->name('tenant.profile.update');
    Route::patch('/profile/password', [\App\Http\Controllers\TenantProfileController::class, 'updatePassword'])
        ->middleware('throttle:sensitive')
        ->name('tenant.profile.password');
    Route::patch('/profile/notification-prefs', [\App\Http\Controllers\TenantProfileController::class, 'updateNotificationPrefs'])->name('tenant.profile.notification-prefs');

    // Phase-28 TENANT-STATEMENT-1/2/3: tenant-facing statement viewer + exports.
    Route::get('/statement', [\App\Http\Controllers\TenantStatementController::class, 'index'])->name('tenant.statement.index');
    Route::get('/statement.pdf', [\App\Http\Controllers\TenantStatementController::class, 'pdf'])->name('tenant.statement.pdf');
    Route::get('/statement.xlsx', [\App\Http\Controllers\TenantStatementController::class, 'xlsx'])->name('tenant.statement.xlsx');
    Route::post('/statement/email', [\App\Http\Controllers\TenantStatementController::class, 'email'])->name('tenant.statement.email');
    // Phase-45 STATEMENT-DEPTH-3: tenant-persisted statement column choice.
    Route::patch('/statement/preferences', [\App\Http\Controllers\TenantStatementController::class, 'updatePreferences'])->name('tenant.statement.preferences');

    // Phase-28 TENANT-DOCS-1/2/3: tenant document repository + downloads.
    Route::get('/documents', [\App\Http\Controllers\TenantDocumentsController::class, 'index'])->name('tenant.documents.index');
    Route::get('/documents/{document}/download', [\App\Http\Controllers\TenantDocumentsController::class, 'download'])->name('tenant.documents.download');

    // Phase-28 TENANT-PAY-1: tenant-initiated payment plan request.
    Route::post('/payment-plans/request', [\App\Http\Controllers\Tenant\PaymentPlanRequestController::class, 'store'])
        ->middleware('throttle:sensitive')
        ->name('tenant.payment-plans.request');
    // Phase-45 PAY-PLAN-MOD-1: tenant proposes new installment schedule after approval.
    Route::post('/payment-plans/{plan}/modifications', [\App\Http\Controllers\Tenant\PaymentPlanModificationController::class, 'store'])
        ->middleware('throttle:sensitive')
        ->name('tenant.payment-plans.modifications.store');
    // Phase-45 EMERGENCY-CONTACT-SMS-1/2: tenant verifies an emergency contact phone.
    Route::post('/emergency-contacts/{contact}/send-otp', [\App\Http\Controllers\Tenant\EmergencyContactVerificationController::class, 'sendOtp'])
        ->middleware('throttle:sensitive')
        ->name('tenant.emergency-contacts.send-otp');
    Route::post('/emergency-contacts/{contact}/verify-otp', [\App\Http\Controllers\Tenant\EmergencyContactVerificationController::class, 'verifyOtp'])
        ->middleware('throttle:sensitive')
        ->name('tenant.emergency-contacts.verify-otp');

    // Phase-28 TENANT-PAY-3: tenant-initiated deposit refund request.
    Route::post('/deposit-refunds', [\App\Http\Controllers\Tenant\DepositRefundController::class, 'store'])
        ->middleware('throttle:sensitive')
        ->name('tenant.deposit-refunds.store');

    // Phase-29 WF-LEASE-RENEW-3: tenant accept/reject of a landlord-
    // proposed renewal.
    Route::post('/renewals/{renewal}/accept', [\App\Http\Controllers\Tenant\RenewalResponseController::class, 'accept'])
        ->middleware('throttle:sensitive')
        ->name('tenant.renewals.accept');
    Route::post('/renewals/{renewal}/reject', [\App\Http\Controllers\Tenant\RenewalResponseController::class, 'reject'])
        ->middleware('throttle:sensitive')
        ->name('tenant.renewals.reject');
    // Phase-45 LEASE-COUNTER-1: tenant submits a counter-offer.
    Route::post('/renewals/{renewal}/counter', [\App\Http\Controllers\Tenant\RenewalResponseController::class, 'counter'])
        ->middleware('throttle:sensitive')
        ->name('tenant.renewals.counter');

    // Phase-63 INBOX-COMPOSE-1: tenant-side message-thread surface.
    Route::get('/inbox', [\App\Http\Controllers\Tenant\InboxController::class, 'index'])
        ->name('tenant.inbox.index');
    Route::get('/inbox/{thread}', [\App\Http\Controllers\Tenant\InboxController::class, 'show'])
        ->name('tenant.inbox.show');
    Route::post('/inbox', [\App\Http\Controllers\Tenant\InboxController::class, 'store'])
        ->middleware('throttle:messages')
        ->name('tenant.inbox.store');
    Route::post('/inbox/{thread}/messages', [\App\Http\Controllers\Tenant\InboxController::class, 'storeMessage'])
        ->middleware('throttle:messages')
        ->name('tenant.inbox.messages.store');
});

// Phase-45 LEASE-COUNTER-2: landlord-side review of a tenant counter-offer.
Route::middleware(['auth', 'verified', 'role:landlord,caretaker'])->group(function () {
    Route::post('/landlords/renewals/{renewal}/counter/accept', [\App\Http\Controllers\LeaseRenewalCounterReviewController::class, 'accept'])
        ->middleware('throttle:sensitive')
        ->name('landlords.renewals.counter.accept');
    Route::post('/landlords/renewals/{renewal}/counter/reject', [\App\Http\Controllers\LeaseRenewalCounterReviewController::class, 'reject'])
        ->middleware('throttle:sensitive')
        ->name('landlords.renewals.counter.reject');
    Route::post('/landlords/renewals/{renewal}/counter/re-propose', [\App\Http\Controllers\LeaseRenewalCounterReviewController::class, 'rePropose'])
        ->middleware('throttle:sensitive')
        ->name('landlords.renewals.counter.re_propose');

    // Phase-63 INBOX-COMPOSE-1: landlord-side message-thread surface.
    Route::get('/message-threads', [\App\Http\Controllers\MessageThreadController::class, 'index'])
        ->name('message-threads.index');
    Route::get('/message-threads/{thread}', [\App\Http\Controllers\MessageThreadController::class, 'show'])
        ->name('message-threads.show');
    Route::post('/message-threads', [\App\Http\Controllers\MessageThreadController::class, 'store'])
        ->middleware('throttle:messages')
        ->name('message-threads.store');
    Route::post('/message-threads/{thread}/messages', [\App\Http\Controllers\MessageThreadController::class, 'storeMessage'])
        ->middleware('throttle:messages')
        ->name('message-threads.messages.store');

    // Phase-63 INBOX-MOD-1: landlord moderation transitions.
    Route::post('/message-threads/{thread}/archive', [\App\Http\Controllers\MessageThreadModerationController::class, 'archive'])
        ->name('message-threads.archive');
    Route::post('/message-threads/{thread}/lock', [\App\Http\Controllers\MessageThreadModerationController::class, 'lock'])
        ->name('message-threads.lock');
    Route::post('/message-threads/{thread}/unlock', [\App\Http\Controllers\MessageThreadModerationController::class, 'unlock'])
        ->name('message-threads.unlock');
});

// Phase-63 INBOX-REALTIME-2: shared read-receipt endpoint. Any
// authenticated participant on the thread can mark a message read.
Route::middleware('auth')->group(function () {
    Route::patch('/messages/{message}/read', \App\Http\Controllers\MessageReadController::class)
        ->name('messages.read');
    // Phase-63 INBOX-MOD-1: sender soft-delete within the 5-min window.
    Route::delete('/messages/{message}', \App\Http\Controllers\MessageDeleteController::class)
        ->name('messages.destroy');
});

// Phase-65 HOLD-UI-1 + BULK-HOLD-2/3: landlord-initiated legal-hold
// CRUD + bulk + tenant-litigation preset. throttle:legal-hold limits
// abuse on the higher-leverage bulk endpoint.
Route::middleware(['auth', 'role:landlord'])->group(function () {
    Route::get('/legal-holds', [\App\Http\Controllers\LegalHoldController::class, 'index'])
        ->name('legal-holds.index');
    Route::post('/legal-holds', [\App\Http\Controllers\LegalHoldController::class, 'store'])
        ->name('legal-holds.store');
    Route::delete('/legal-holds/{legalHold}', [\App\Http\Controllers\LegalHoldController::class, 'destroy'])
        ->name('legal-holds.destroy');

    Route::middleware('throttle:legal-hold')->group(function () {
        Route::post('/legal-holds/bulk', [\App\Http\Controllers\LegalHoldBulkController::class, 'store'])
            ->name('legal-holds.bulk.store');
        Route::delete('/legal-holds/bulk', [\App\Http\Controllers\LegalHoldBulkController::class, 'destroy'])
            ->name('legal-holds.bulk.destroy');
        Route::post('/tenants/{tenant}/legal-hold', \App\Http\Controllers\TenantLegalHoldController::class)
            ->name('tenants.legal-hold');
    });

    Route::get('/legal-holds/audit-export', \App\Http\Controllers\LegalHoldAuditExportController::class)
        ->name('legal-holds.audit-export');
});

// Phase-66 NPS-SURVEY-1/2: in-app NPS for every authenticated customer
// (landlord/caretaker/tenant). Eligibility + cadence are enforced
// server-side in NpsEligibilityService — these endpoints only record
// the outcome of a prompt the server already decided to show. Throttled
// so a tampered client cannot hammer the state-write endpoints.
Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::post('/nps', [\App\Http\Controllers\NpsResponseController::class, 'store'])
        ->name('nps.store');
    Route::post('/nps/impression', [\App\Http\Controllers\NpsResponseController::class, 'impression'])
        ->name('nps.impression');
    Route::post('/nps/dismiss', [\App\Http\Controllers\NpsResponseController::class, 'dismiss'])
        ->name('nps.dismiss');
    Route::post('/nps/opt-out', [\App\Http\Controllers\NpsResponseController::class, 'optOut'])
        ->name('nps.opt-out');
});

// Phase-29 WF-LEASE-RENEW-2: landlord-side renewal initiate + confirm.
Route::middleware(['auth', 'role:landlord'])->group(function () {
    Route::post('/leases/{lease}/renewals', [\App\Http\Controllers\LeaseRenewalController::class, 'store'])
        ->name('leases.renewals.store');
    Route::post('/renewals/{renewal}/confirm', [\App\Http\Controllers\LeaseRenewalController::class, 'confirm'])
        ->name('renewals.confirm');

    // Phase-29 WF-PAY-APPROVE-1: landlord approves/rejects tenant-
    // requested payment plans (closes Phase-28 deferred UI).
    Route::post('/payment-plans/{plan}/approve', [\App\Http\Controllers\Finance\PaymentPlanApprovalController::class, 'approve'])
        ->name('finance.payment-plans.approve');
    Route::post('/payment-plans/{plan}/reject', [\App\Http\Controllers\Finance\PaymentPlanApprovalController::class, 'reject'])
        ->name('finance.payment-plans.reject');
    // Phase-45 PAY-PLAN-MOD-2: landlord-side approve/reject of a tenant modification request.
    Route::post('/payment-plan-modifications/{modification}/approve', [\App\Http\Controllers\PaymentPlanModificationReviewController::class, 'approve'])
        ->name('finance.payment-plan-modifications.approve');
    Route::post('/payment-plan-modifications/{modification}/reject', [\App\Http\Controllers\PaymentPlanModificationReviewController::class, 'reject'])
        ->name('finance.payment-plan-modifications.reject');

    // Phase-29 WF-PAY-APPROVE-2: landlord processes tenant-requested
    // deposit refunds (closes Phase-28 deferred UI).
    Route::post('/deposit-refunds/{refund}/approve', [\App\Http\Controllers\Finance\DepositRefundApprovalController::class, 'approve'])
        ->name('finance.deposit-refunds.approve');
    Route::post('/deposit-refunds/{refund}/reject', [\App\Http\Controllers\Finance\DepositRefundApprovalController::class, 'reject'])
        ->name('finance.deposit-refunds.reject');
    Route::post('/deposit-refunds/{refund}/mark-paid', [\App\Http\Controllers\Finance\DepositRefundApprovalController::class, 'markPaid'])
        ->name('finance.deposit-refunds.mark-paid');
    // Phase-30 INT-MPESA-DEEP-1: B2C payout for approved refund
    Route::post('/deposit-refunds/{refund}/pay-mpesa', [\App\Http\Controllers\Finance\DepositRefundApprovalController::class, 'payViaMpesa'])
        ->name('finance.deposit-refunds.pay-mpesa');
});

// --- LEGAL DOCUMENTS (Public) ---
Route::get('/legal/{type}', [ConsentController::class, 'view'])->name('legal.view');

// --- CONSENT & GDPR ROUTES ---
Route::middleware('auth')->group(function () {
    // Consent Management
    Route::prefix('consent')->name('consent.')->group(function () {
        Route::get('/required', [ConsentController::class, 'required'])->name('required');
        Route::post('/accept', [ConsentController::class, 'accept'])->name('accept');
        Route::get('/history', [ConsentController::class, 'history'])->name('history');
        // Phase-13 DPA-1: generic consent withdrawal replaces the
        // marketing-only path. Article 7(3) requires withdrawing be
        // as easy as granting — one route + a 'type' body param covers
        // every withdrawable consent kind.
        Route::post('/withdraw', [ConsentController::class, 'withdrawConsent'])->name('withdraw');
    });

    // GDPR Privacy Settings
    Route::prefix('privacy')->name('gdpr.')->group(function () {
        Route::get('/', [GdprController::class, 'index'])->name('index');
        // RATE-7: every export path bound to throttle:export — these
        // touch the full account dataset and ZIP+sign it; an unbounded
        // loop is a cheap DoS on the worker.
        Route::post('/export', [GdprController::class, 'requestExport'])
            ->middleware('throttle:export')
            ->name('request-export');
        Route::get('/export/download', [GdprController::class, 'downloadExport'])
            ->middleware('throttle:export')
            ->name('download-export');
        Route::get('/export/immediate', [GdprController::class, 'immediateExport'])
            ->middleware('throttle:export')
            ->name('immediate-export');
        Route::post('/delete', [GdprController::class, 'requestDeletion'])
            ->middleware('throttle:sensitive')
            ->name('request-deletion');
        Route::post('/delete/cancel', [GdprController::class, 'cancelDeletion'])->name('cancel-deletion');

        // Phase-13 DPA-4: Article 18 right to restriction of
        // processing. Sets restricted_at on the user; the Gate hook
        // in AuthServiceProvider denies write-side abilities while
        // restricted. Release path clears it.
        Route::post('/restrict', [GdprController::class, 'requestRestriction'])
            ->middleware('throttle:sensitive')
            ->name('request-restriction');
        Route::post('/restrict/release', [GdprController::class, 'releaseRestriction'])
            ->middleware('throttle:sensitive')
            ->name('release-restriction');

        // Phase-13 DPA-5: Article 21 right to object. Records an
        // objection to a legitimate_interests processing operation.
        // The catalog of objectable categories is in
        // ConsentController::OBJECTABLE_CATEGORIES.
        Route::post('/object', [ConsentController::class, 'objectToProcessing'])
            ->name('object');
    });
});

require __DIR__.'/auth.php';
