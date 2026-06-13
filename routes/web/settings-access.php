<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\PaymentsHubController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\WaterSettingsController;
use Illuminate\Support\Facades\Route;

// 16. Deposits (Security Deposits Tracking) — superseded by the Finances
// deposits tab; redirect (name kept for any server-side reference).
Route::get('/deposits', fn () => redirect()->route('finances.deposits'))->name('deposits.index');

// 17. Arrears (Overdue Tracking) — superseded by the Finances arrears tab.
Route::get('/arrears', fn () => redirect()->route('finances.arrears'))->name('arrears.index');

// 18. Water Settings (Global Water Billing Configuration). NOT gated by
// water.module — this (with buildings.water-settings) is how a landlord
// ENABLES water billing, so gating it would dead-end a "no water billing"
// landlord who later wants to start charging. Controller stays
// landlord/caretaker-only.
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

// 18. Payout Accounts Management (Landlords Only) — back-compat bookmark only.
// All actions and the full UI live in Payments Hub (payments-hub.collection).
Route::middleware('role:landlord')
    ->get('/settings/payout', fn () => redirect()->route('payments-hub.collection'))
    ->name('settings.payout.index');

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
