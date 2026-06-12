<?php

use App\Http\Controllers\Admin\AdminBillingController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

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
        // analytics/history pages were never built; the billing index page
        // covers these client-side. Redirect to avoid a dead render target.
        Route::get('/analytics', fn () => redirect()->route('admin.billing.index'))->name('analytics');
        Route::get('/history', fn () => redirect()->route('admin.billing.index'))->name('history');
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
