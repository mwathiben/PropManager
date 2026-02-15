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

// --- SIGNED EMAIL ROUTES (no auth required) ---
Route::get('/email/preferences', [\App\Http\Controllers\NotificationsController::class, 'emailPreferences'])
    ->name('email.preferences')
    ->middleware('signed');

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
    Route::prefix('onboarding')->name('onboarding.')->group(function () {
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
    Route::get('/leases/{lease}/download', [LeaseController::class, 'download'])->name('leases.download');

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
    Route::get('/tenants/{tenant}/ledger/pdf', [TenantController::class, 'ledgerPdf'])->name('tenants.ledger.pdf');
    Route::post('/tenants/{tenant}/ledger/email', [TenantController::class, 'ledgerEmail'])->name('tenants.ledger.email');

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
        Route::post('/{message}/reply', [InboxController::class, 'reply'])->name('reply');
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
    Route::post('/invoices/{invoice}/send-reminder', [InvoiceController::class, 'sendReminder'])->name('invoices.send-reminder');
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
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
    Route::get('/payments/callback', [PaymentController::class, 'handleCallback'])->name('payments.callback');
    Route::get('/payments/public-key', [PaymentController::class, 'getPublicKey'])->name('payments.publicKey');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'downloadReceipt'])->name('payments.downloadReceipt');
    Route::post('/payments/{payment}/send-receipt', [PaymentController::class, 'sendReceipt'])->name('payments.send-receipt');
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

    // 12. Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationsController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/overview', [\App\Http\Controllers\NotificationsController::class, 'overview'])->name('notifications.overview');
    Route::post('/notifications/send', [\App\Http\Controllers\NotificationsController::class, 'send'])->name('notifications.send');
    Route::post('/notifications/send-bulk', [\App\Http\Controllers\NotificationsController::class, 'sendBulk'])->name('notifications.sendBulk');
    Route::post('/notifications/rent-reminders', [\App\Http\Controllers\NotificationsController::class, 'sendRentReminders'])->name('notifications.sendRentReminders');
    Route::post('/notifications/arrears-notices', [\App\Http\Controllers\NotificationsController::class, 'sendArrearsNotices'])->name('notifications.sendArrearsNotices');
    Route::get('/notifications/preferences', [\App\Http\Controllers\NotificationsController::class, 'getPreferences'])->name('notifications.preferences');
    Route::post('/notifications/preferences', [\App\Http\Controllers\NotificationsController::class, 'updatePreferences'])->name('notifications.updatePreferences');
    Route::post('/notifications/{notification}/mark-read', [\App\Http\Controllers\NotificationsController::class, 'markAsRead'])->name('notifications.markAsRead');
    Route::post('/notifications/{notification}/retry', [\App\Http\Controllers\NotificationsController::class, 'retry'])->name('notifications.retry');
    Route::delete('/notifications/{notification}', [\App\Http\Controllers\NotificationsController::class, 'destroy'])->name('notifications.destroy');

    // Notification Templates
    Route::get('/notifications/templates', [\App\Http\Controllers\NotificationsController::class, 'templates'])->name('notifications.templates');
    Route::post('/notifications/templates', [\App\Http\Controllers\NotificationsController::class, 'storeTemplate'])->name('notifications.templates.store');
    Route::put('/notifications/templates/{template}', [\App\Http\Controllers\NotificationsController::class, 'updateTemplate'])->name('notifications.templates.update');
    Route::delete('/notifications/templates/{template}', [\App\Http\Controllers\NotificationsController::class, 'destroyTemplate'])->name('notifications.templates.destroy');
    Route::post('/notifications/templates/{template}/preview', [\App\Http\Controllers\NotificationsController::class, 'previewTemplate'])->name('notifications.templates.preview');

    // Notification Schedules
    Route::get('/notifications/schedules', [\App\Http\Controllers\NotificationsController::class, 'schedules'])->name('notifications.schedules');
    Route::post('/notifications/schedules', [\App\Http\Controllers\NotificationsController::class, 'storeSchedule'])->name('notifications.schedules.store');
    Route::put('/notifications/schedules/{schedule}', [\App\Http\Controllers\NotificationsController::class, 'updateSchedule'])->name('notifications.schedules.update');
    Route::delete('/notifications/schedules/{schedule}', [\App\Http\Controllers\NotificationsController::class, 'destroySchedule'])->name('notifications.schedules.destroy');
    Route::post('/notifications/schedules/{schedule}/toggle', [\App\Http\Controllers\NotificationsController::class, 'toggleSchedule'])->name('notifications.schedules.toggle');
    Route::post('/notifications/schedules/{schedule}/run', [\App\Http\Controllers\NotificationsController::class, 'runScheduleNow'])->name('notifications.schedules.run');

    // Notification Settings
    Route::get('/notifications/settings', [\App\Http\Controllers\NotificationsController::class, 'settings'])->name('notifications.settings');
    Route::post('/notifications/settings/provider/{provider}', [\App\Http\Controllers\NotificationsController::class, 'updateProviderSettings'])->name('notifications.settings.provider');
    Route::post('/notifications/settings/test/{provider}', [\App\Http\Controllers\NotificationsController::class, 'testProvider'])->name('notifications.settings.test');
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
    Route::post('/bulk-operations/adjust-rent', [\App\Http\Controllers\BulkOperationsController::class, 'adjustRent'])->name('bulk.adjustRent');
    Route::post('/bulk-operations/update-unit-status', [\App\Http\Controllers\BulkOperationsController::class, 'updateUnitStatus'])->name('bulk.updateUnitStatus');
    Route::post('/bulk-operations/terminate-leases', [\App\Http\Controllers\BulkOperationsController::class, 'terminateLeases'])->name('bulk.terminateLeases');
    Route::post('/bulk-operations/extend-leases', [\App\Http\Controllers\BulkOperationsController::class, 'extendLeases'])->name('bulk.extendLeases');
    Route::post('/bulk-operations/adjust-deposits', [\App\Http\Controllers\BulkOperationsController::class, 'adjustDeposits'])->name('bulk.adjustDeposits');
    Route::post('/bulk-operations/update-target-rent', [\App\Http\Controllers\BulkOperationsController::class, 'updateTargetRent'])->name('bulk.updateTargetRent');
    Route::post('/bulk-operations/update-meter-numbers', [\App\Http\Controllers\BulkOperationsController::class, 'updateMeterNumbers'])->name('bulk.updateMeterNumbers');

    // 14. Tickets (Issues & Complaints)
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])
        ->middleware('throttle:file-upload')
        ->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::put('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::post('/tickets/{ticket}/comment', [TicketController::class, 'addComment'])->name('tickets.comment');
    Route::post('/tickets/{ticket}/resolve', [TicketController::class, 'resolve'])->name('tickets.resolve');
    Route::post('/tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');
    Route::post('/tickets/{ticket}/feedback', [TicketController::class, 'submitFeedback'])->name('tickets.feedback');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
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
        Route::get('/banks', [PaymentsHubController::class, 'getBanks'])
            ->middleware('throttle:api')
            ->name('banks');
        Route::post('/verify-account', [PaymentsHubController::class, 'verifyAccount'])->name('verify-account');
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

    // 16. Help Center (All Users)
    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::get('/help/search', [HelpController::class, 'search'])
        ->middleware('throttle:search')
        ->name('help.search');
    Route::get('/help/{article:slug}', [HelpController::class, 'show'])->name('help.show');

    // 17. Subscription Management (Landlords Only)
    Route::middleware('role:landlord')->prefix('subscription')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index'])->name('subscription.index');
        Route::get('/plans', [SubscriptionController::class, 'plans'])->name('subscription.plans');
        Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
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
        Route::get('/api/banks', [PaymentsHubController::class, 'getBanks'])
            ->middleware('throttle:api')
            ->name('api.banks');
        Route::post('/api/verify-account', [PaymentsHubController::class, 'verifyAccount'])->name('api.verify-account');
    });

    // 19. KYC Review Routes (Landlords and Caretakers)
    Route::middleware('role:landlord,caretaker')->group(function () {
        Route::get('/kyc/pending', [\App\Http\Controllers\TenantKycController::class, 'pendingReviews'])->name('kyc.pending');
        Route::post('/kyc/submissions/{submission}/review', [\App\Http\Controllers\TenantKycController::class, 'review'])->name('kyc.review');
    });
});

// --- SUPER ADMIN ROUTES ---
Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->group(function () {
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
        Route::post('/withdraw-marketing', [ConsentController::class, 'withdrawMarketing'])->name('withdraw-marketing');
    });

    // GDPR Privacy Settings
    Route::prefix('privacy')->name('gdpr.')->group(function () {
        Route::get('/', [GdprController::class, 'index'])->name('index');
        Route::post('/export', [GdprController::class, 'requestExport'])->name('request-export');
        Route::get('/export/download', [GdprController::class, 'downloadExport'])->name('download-export');
        Route::get('/export/immediate', [GdprController::class, 'immediateExport'])->name('immediate-export');
        Route::post('/delete', [GdprController::class, 'requestDeletion'])
            ->middleware('throttle:sensitive')
            ->name('request-deletion');
        Route::post('/delete/cancel', [GdprController::class, 'cancelDeletion'])->name('cancel-deletion');
    });
});

require __DIR__.'/auth.php';
