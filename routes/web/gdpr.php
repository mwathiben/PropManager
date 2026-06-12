<?php

use App\Http\Controllers\ConsentController;
use App\Http\Controllers\GdprController;
use Illuminate\Support\Facades\Route;

// --- LEGAL DOCUMENTS (Public) ---
Route::get('/legal/{type}', [ConsentController::class, 'view'])->name('legal.view');

// --- CONSENT & GDPR ROUTES ---
Route::middleware('auth')->group(function () {
    // Consent Management
    Route::prefix('consent')->name('consent.')->group(function () {
        Route::get('/required', [ConsentController::class, 'required'])->name('required');
        Route::post('/accept', [ConsentController::class, 'accept'])->name('accept');
        // Consent-history page was never built; the privacy centre covers it.
        Route::get('/history', fn () => redirect()->route('gdpr.index'))->name('history');
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
