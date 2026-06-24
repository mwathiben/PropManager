<?php

use App\Http\Controllers\AgreementController;
use Illuminate\Support\Facades\Route;

/*
 * Slice-2: management-agreement composer (manager-only). Required inside the
 * authenticated group in routes/web.php, so `auth` is already applied here.
 */
Route::middleware('role:manager')->group(function () {
    Route::get('/agreements', [AgreementController::class, 'index'])->name('agreements.index');
    Route::get('/agreements/create', [AgreementController::class, 'create'])->name('agreements.create');
    Route::post('/agreements', [AgreementController::class, 'store'])->name('agreements.store');
    Route::get('/agreements/{agreement}', [AgreementController::class, 'show'])->name('agreements.show');
});
