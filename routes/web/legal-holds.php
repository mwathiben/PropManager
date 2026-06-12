<?php

use Illuminate\Support\Facades\Route;

// Phase-65 HOLD-UI-1 + BULK-HOLD-2/3: landlord-initiated legal-hold
// CRUD + bulk + tenant-litigation preset. throttle:legal-hold limits
// abuse on the higher-leverage bulk endpoint.
Route::middleware(['auth', 'role:landlord'])->group(function () {
    Route::get('/legal-holds', [\App\Http\Controllers\LegalHoldController::class, 'index'])
        ->name('legal-holds.index');
    // Phase-72 COMMAND-CENTER: the flat list (formerly the index landing).
    Route::get('/legal-holds/list', [\App\Http\Controllers\LegalHoldController::class, 'list'])
        ->name('legal-holds.list');
    Route::post('/legal-holds', [\App\Http\Controllers\LegalHoldController::class, 'store'])
        ->name('legal-holds.store');
    Route::delete('/legal-holds/{legalHold}', [\App\Http\Controllers\LegalHoldController::class, 'destroy'])
        ->whereNumber('legalHold')
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

    // Phase-68 HISTORY-1/2: per-subject hold/release timeline + CSV.
    Route::get('/legal-holds/history', [\App\Http\Controllers\LegalHoldHistoryController::class, 'show'])
        ->name('legal-holds.history');
    Route::get('/legal-holds/history/export', [\App\Http\Controllers\LegalHoldHistoryController::class, 'export'])
        ->name('legal-holds.history.export');

    // Phase-72 SUBJECT-PICKER: suggest a tenant's holdable records for the wizard.
    Route::get('/legal-holds/subjects/suggest', [\App\Http\Controllers\LegalHoldSubjectController::class, 'suggest'])
        ->name('legal-holds.subjects.suggest');

    // Phase-72 WIZARD-FLOW: guided create-hold wizard.
    Route::get('/legal-holds/wizard', [\App\Http\Controllers\LegalHoldWizardController::class, 'create'])
        ->name('legal-holds.wizard');
    Route::post('/legal-holds/wizard', [\App\Http\Controllers\LegalHoldWizardController::class, 'store'])
        ->middleware('throttle:legal-hold')
        ->name('legal-holds.wizard.store');

    // Phase-72 HOLD-SETTINGS: per-landlord legal-hold preferences.
    Route::get('/legal-holds/settings', [\App\Http\Controllers\LegalHoldSettingsController::class, 'show'])
        ->name('legal-holds.settings');
    Route::put('/legal-holds/settings', [\App\Http\Controllers\LegalHoldSettingsController::class, 'update'])
        ->name('legal-holds.settings.update');

    // Phase-76 AUTO-APPLY-3: per-landlord wallet auto-apply mode.
    Route::get('/wallet/settings', [\App\Http\Controllers\WalletSettingsController::class, 'show'])
        ->name('wallet.settings');
    Route::put('/wallet/settings', [\App\Http\Controllers\WalletSettingsController::class, 'update'])
        ->name('wallet.settings.update');

    // Phase-72 MATTER-GROUPING: case-level grouping of holds.
    Route::get('/legal-matters', [\App\Http\Controllers\LegalMatterController::class, 'index'])
        ->name('legal-matters.index');
    Route::get('/legal-matters/{matter}', [\App\Http\Controllers\LegalMatterController::class, 'show'])
        ->whereNumber('matter')
        ->name('legal-matters.show');
    Route::get('/legal-matters/{matter}/audit-export', [\App\Http\Controllers\LegalMatterController::class, 'auditExport'])
        ->whereNumber('matter')
        ->name('legal-matters.audit-export');
    Route::middleware('throttle:legal-hold')->group(function () {
        Route::post('/legal-matters/{matter}/release', [\App\Http\Controllers\LegalMatterController::class, 'release'])
            ->whereNumber('matter')
            ->name('legal-matters.release');
        Route::post('/legal-matters/{matter}/close', [\App\Http\Controllers\LegalMatterController::class, 'close'])
            ->whereNumber('matter')
            ->name('legal-matters.close');
        Route::post('/legal-matters/{matter}/reopen', [\App\Http\Controllers\LegalMatterController::class, 'reopen'])
            ->whereNumber('matter')
            ->name('legal-matters.reopen');
    });
});
