<?php

use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceSettingController;
use App\Http\Controllers\InvoiceTemplateController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReceiptTemplateController;
use App\Http\Controllers\TenantPaymentVerificationController;
use Illuminate\Support\Facades\Route;

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
// Phase-82 DOC-RENEWAL-1: renew (supersede) an expiring document.
Route::post('/documents/{document}/renew', [DocumentController::class, 'renew'])
    ->middleware(['throttle:file-upload', 'role:landlord,manager,caretaker'])
    ->whereNumber('document')->name('documents.renew');
// Phase-82 NOTICE-GEN-1: generate a notice PDF stored as a Document on a lease.
Route::post('/leases/{lease}/generate-notice', [DocumentController::class, 'generateNotice'])
    ->middleware('role:landlord,manager,caretaker')
    ->whereNumber('lease')->name('documents.generate-notice');
// Phase-83 LEASE-DOC-GEN-1: generate the lease-agreement PDF as a Document.
Route::post('/leases/{lease}/generate-lease', [DocumentController::class, 'generateLeaseAgreement'])
    ->middleware('role:landlord,manager,caretaker')
    ->whereNumber('lease')->name('documents.generate-lease');
// Phase-83 LEASE-DOC-GEN-2: generate a renewal-offer PDF as a Document.
Route::post('/renewals/{renewal}/generate-offer', [DocumentController::class, 'generateRenewalOffer'])
    ->middleware('role:landlord,manager,caretaker')
    ->whereNumber('renewal')->name('documents.generate-renewal-offer');
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

// Refunds. The standalone refund pages were superseded by the Finances
// refunds tab; these GET routes redirect there (names kept — server-side
// redirects still resolve). POST actions remain functional.
Route::get('/refunds', fn () => redirect()->route('finances.refunds'))->name('refunds.index');
Route::get('/payments/{payment}/refund', fn () => redirect()->route('finances.refunds'))->name('refunds.create');
Route::post('/payments/{payment}/refund', [\App\Http\Controllers\RefundController::class, 'store'])->name('refunds.store');
Route::get('/refunds/{refund}', fn () => redirect()->route('finances.refunds'))->name('refunds.show');
Route::post('/refunds/{refund}/process', [\App\Http\Controllers\RefundController::class, 'process'])->name('refunds.process');
Route::post('/refunds/{refund}/cancel', [\App\Http\Controllers\RefundController::class, 'cancel'])->name('refunds.cancel');

// Credit Notes
Route::get('/credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');
Route::get('/credit-notes/create', [CreditNoteController::class, 'create'])->name('credit-notes.create');
Route::post('/credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');
Route::get('/credit-notes/{creditNote}', [CreditNoteController::class, 'show'])->name('credit-notes.show');
Route::post('/credit-notes/{creditNote}/approve', [CreditNoteController::class, 'approve'])->name('credit-notes.approve');
Route::post('/credit-notes/{creditNote}/apply', [CreditNoteController::class, 'apply'])->name('credit-notes.apply');
Route::post('/credit-notes/{creditNote}/apply-to-wallet', [CreditNoteController::class, 'applyToWallet'])->name('credit-notes.apply-to-wallet');
Route::post('/credit-notes/{creditNote}/void', [CreditNoteController::class, 'void'])->name('credit-notes.void');
Route::get('/credit-notes/{creditNote}/download', [CreditNoteController::class, 'downloadPdf'])->name('credit-notes.download');
Route::get('/tenants/{tenant}/credit-notes', [CreditNoteController::class, 'forTenant'])->name('tenants.credit-notes');

// Bank Reconciliation
Route::get('/reconciliation', fn () => redirect()->route('finances.reconciliation'))->name('reconciliation.index');
Route::post('/reconciliation/{item}/match', [\App\Http\Controllers\ReconciliationController::class, 'match'])->name('reconciliation.match');
Route::post('/reconciliation/{item}/retry', [\App\Http\Controllers\ReconciliationController::class, 'retry'])->name('reconciliation.retry');
Route::delete('/reconciliation/{item}', [\App\Http\Controllers\ReconciliationController::class, 'destroy'])->name('reconciliation.destroy');
Route::post('/reconciliation/import', [\App\Http\Controllers\ReconciliationController::class, 'import'])->name('reconciliation.import');
Route::post('/reconciliation/process-queue', [\App\Http\Controllers\ReconciliationController::class, 'processQueue'])->name('reconciliation.process-queue');

// Phase-85 RECON-VIEW: gateway (Paystack/Stripe) reconciliation report viewer.
Route::get('/gateway-reconciliation', [\App\Http\Controllers\GatewayReconciliationController::class, 'index'])
    ->middleware('role:landlord,manager,caretaker')->name('gateway-reconciliation.index');
Route::get('/gateway-reconciliation/{report}', [\App\Http\Controllers\GatewayReconciliationController::class, 'show'])
    ->middleware('role:landlord,manager,caretaker')->whereNumber('report')->name('gateway-reconciliation.show');

// 9. Settings (Integrations & Configuration)
Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings/business-profile', [\App\Http\Controllers\SettingsController::class, 'updateBusinessProfile'])->name('settings.business.update');
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
Route::get('/reports/export/pdf', fn () => redirect()->route('finances.reports.export', ['format' => 'pdf']))->name('reports.export.pdf');
Route::get('/reports/export/excel', fn () => redirect()->route('finances.reports.export', ['format' => 'xlsx']))->name('reports.export.excel');
Route::get('/reports/metrics', fn () => redirect()->route('finances.reports'));
