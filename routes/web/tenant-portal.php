<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TenantFinancesController;
use App\Http\Controllers\TenantPaymentVerificationController;
use App\Http\Controllers\TenantPortalController;
use Illuminate\Support\Facades\Route;

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

// --- WATER-CLIENT ROUTES (Phase-97) — the water client's own charges + balance.
// No lease/payment-verification/KYC: a water client is a billed connection, not a tenant.
Route::middleware(['auth', 'verified', 'role:water_client'])->prefix('water-client')->name('water-client.')->group(function () {
    Route::get('/finances', [\App\Http\Controllers\WaterClientFinancesController::class, 'index'])->name('finances');
    // Phase-99: a water client pays their own invoice online (gateway-agnostic checkout).
    Route::get('/finances/pay/{invoice}', [\App\Http\Controllers\WaterClientFinancesController::class, 'pay'])
        ->whereNumber('invoice')->name('finances.pay');
});

// --- OWNER-PORTAL ROUTES (Phase-102) — the owner's own view of the properties a PM
// manages for them + their statements. No lease/KYC; scoped to their PropertyOwner.
Route::middleware(['auth', 'verified', 'role:owner'])->prefix('owner-portal')->name('owner-portal.')->group(function () {
    Route::get('/', [\App\Http\Controllers\OwnerPortalDashboardController::class, 'index'])->name('dashboard');
    Route::get('/statements', [\App\Http\Controllers\OwnerPortalStatementsController::class, 'index'])->name('statements');
    Route::get('/statements/download', [\App\Http\Controllers\OwnerPortalStatementsController::class, 'download'])
        ->middleware('throttle:export')->name('statements.download');
    // Phase-103 OWNER-PAYOUTS: the owner's read-only view of what's been remitted + balance.
    Route::get('/payouts', [\App\Http\Controllers\OwnerPortalPayoutsController::class, 'index'])->name('payouts');
    // Phase-104 OWNER-REMITTANCE-NOTIFY: the owner's notifications (payouts + statements).
    Route::get('/notifications', [\App\Http\Controllers\OwnerPortalNotificationsController::class, 'index'])->name('notifications');
    Route::patch('/notifications/read-all', [\App\Http\Controllers\OwnerPortalNotificationsController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [\App\Http\Controllers\OwnerPortalNotificationsController::class, 'markAsRead'])->whereNumber('notification')->name('notifications.read');
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

    // Phase-79 WATER-GATE-4: read-only tenant water view, only when the
    // landlord charges for water (conditional module).
    Route::get('/water', [TenantPortalController::class, 'water'])
        ->middleware('water.module')->name('tenant.water');

    // Tenant Finances (New Simplified Payment Flow)
    Route::get('/finances', [TenantFinancesController::class, 'index'])->name('tenant.finances.index');
    Route::get('/finances/pay/{invoice}', [TenantFinancesController::class, 'pay'])->name('tenant.finances.pay');
    Route::get('/finances/history', [TenantFinancesController::class, 'history'])->name('tenant.finances.history');

    // Phase-76 TENANT-APPLY: tenant self-service wallet (view + apply to own invoices)
    Route::get('/wallet', [\App\Http\Controllers\TenantWalletController::class, 'index'])->name('tenant.wallet.index');
    Route::post('/wallet/apply', [\App\Http\Controllers\TenantWalletController::class, 'apply'])
        ->middleware('throttle:6,1')->name('tenant.wallet.apply');

    // Phase-84 PAY-METHODS: tenant self-management of saved payment methods.
    Route::get('/payment-methods', [\App\Http\Controllers\Tenant\PaymentMethodController::class, 'index'])->name('tenant.payment-methods.index');
    Route::post('/payment-methods', [\App\Http\Controllers\Tenant\PaymentMethodController::class, 'store'])
        ->middleware('throttle:sensitive')->name('tenant.payment-methods.store');
    Route::patch('/payment-methods/{paymentMethod}/default', [\App\Http\Controllers\Tenant\PaymentMethodController::class, 'setDefault'])
        ->whereNumber('paymentMethod')->name('tenant.payment-methods.default');
    Route::delete('/payment-methods/{paymentMethod}', [\App\Http\Controllers\Tenant\PaymentMethodController::class, 'destroy'])
        ->whereNumber('paymentMethod')->name('tenant.payment-methods.destroy');

    // Phase-84 INVOICE-PDF-1: tenant per-invoice PDF download.
    Route::get('/invoices/{invoice}/download', [TenantFinancesController::class, 'downloadInvoice'])
        ->whereNumber('invoice')->name('tenant.invoices.download');

    // Phase-84 RENEWAL-RESPONSE-1: dedicated tenant renewal review page.
    Route::get('/renewals', [\App\Http\Controllers\Tenant\RenewalResponseController::class, 'index'])->name('tenant.renewals.index');

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
        ->whereNumber('thread')
        ->name('tenant.inbox.show');
    Route::post('/inbox', [\App\Http\Controllers\Tenant\InboxController::class, 'store'])
        ->middleware('throttle:messages')
        ->name('tenant.inbox.store');
    Route::post('/inbox/{thread}/messages', [\App\Http\Controllers\Tenant\InboxController::class, 'storeMessage'])
        ->middleware('throttle:messages')
        ->name('tenant.inbox.messages.store');

    // Phase-67 READ-RECEIPTS-1: mark the whole thread read.
    Route::post('/inbox/{thread}/read-all', \App\Http\Controllers\MessageThreadReadAllController::class)
        ->name('tenant.inbox.read-all');

    // Phase-67 MESSAGE-SEARCH-2: participant-scoped full-text search.
    Route::get('/inbox/search', [\App\Http\Controllers\Tenant\InboxSearchController::class, 'index'])
        ->middleware('throttle:messages')
        ->name('tenant.inbox.search');

    // Phase-71 REACTIONS: toggle an emoji reaction (participant-gated).
    Route::post('/inbox/{thread}/messages/{message}/reactions', [\App\Http\Controllers\MessageReactionController::class, 'toggle'])
        ->whereNumber('thread')
        ->whereNumber('message')
        ->middleware('throttle:reactions')
        ->name('tenant.inbox.messages.react');

    // Phase-71 MEDIA-CI: participant-gated message attachment (image/file).
    Route::get('/inbox/{thread}/messages/{message}/attachments/{document}', [\App\Http\Controllers\MessageAttachmentController::class, 'show'])
        ->whereNumber('thread')
        ->whereNumber('message')
        ->whereNumber('document')
        ->name('tenant.inbox.attachments.show');
});
