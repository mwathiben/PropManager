<?php

use Illuminate\Support\Facades\Route;

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
