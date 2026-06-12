<?php

use App\Http\Controllers\Finance\DepositController as FinanceDepositController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\FinanceNotificationController;
use App\Http\Controllers\Finance\FinanceReportController;
use App\Http\Controllers\Finance\FinanceSettingsController;
use App\Http\Controllers\Finance\FinanceTemplateController;
use App\Http\Controllers\Finance\LateFeeController;
use App\Http\Controllers\FinancesController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentsHubController;
use App\Http\Controllers\RefundController;
use Illuminate\Support\Facades\Route;

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
    // Phase-100 REPORTS-DEPTH-3: rent-roll snapshot + per-property P&L (pdf/xlsx/csv).
    Route::get('/reports/rent-roll', [FinanceReportController::class, 'rentRoll'])
        ->middleware('throttle:export')
        ->name('reports.rent-roll');
    Route::get('/reports/property-pnl', [FinanceReportController::class, 'propertyPnl'])
        ->middleware('throttle:export')
        ->name('reports.property-pnl');
    Route::get('/reports/owner-statement', [FinanceReportController::class, 'ownerStatement'])
        ->middleware('throttle:export')
        ->name('reports.owner-statement');

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
    // Phase-81 LATE-FEE-DEPTH-1: apply late fees to eligible overdue invoices on demand.
    Route::post('/late-fees/apply-now', [LateFeeController::class, 'applyNow'])->name('late-fees.apply-now');
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
    // Phase-70 VENDOR-AUTH-3: re-send a fresh signed portal link.
    Route::post('/vendors/{vendor}/portal-link', [\App\Http\Controllers\VendorPortalController::class, 'reissue'])
        ->name('vendors.portal-link');

    // Phase-101 OWNER-FOUNDATION: property owners (a PM manages on their behalf).
    Route::get('/owners', [\App\Http\Controllers\PropertyOwnerController::class, 'index'])->name('owners.index');
    Route::post('/owners', [\App\Http\Controllers\PropertyOwnerController::class, 'store'])->name('owners.store');
    Route::put('/owners/{owner}', [\App\Http\Controllers\PropertyOwnerController::class, 'update'])->name('owners.update');
    Route::delete('/owners/{owner}', [\App\Http\Controllers\PropertyOwnerController::class, 'destroy'])->name('owners.destroy');
    Route::get('/owners/{owner}/statement', [\App\Http\Controllers\PropertyOwnerController::class, 'statement'])
        ->middleware('throttle:export')->name('owners.statement');
    Route::post('/owners/{owner}/statement/email', [\App\Http\Controllers\PropertyOwnerController::class, 'emailStatement'])
        ->middleware('throttle:notification-send')->name('owners.statement.email');
    // Phase-102: invite an owner to create a portal login.
    Route::post('/owners/{owner}/invite', [\App\Http\Controllers\OwnerInvitationController::class, 'store'])
        ->whereNumber('owner')->middleware('throttle:invitation')->name('owners.invite');
    // Phase-103 OWNER-PAYOUTS: owner detail (fee + balance + payout history) + record/void.
    Route::get('/owners/{owner}', [\App\Http\Controllers\PropertyOwnerController::class, 'show'])
        ->whereNumber('owner')->name('owners.show');
    Route::post('/owners/{owner}/payouts', [\App\Http\Controllers\OwnerPayoutController::class, 'store'])
        ->whereNumber('owner')->name('owners.payouts.store');
    Route::post('/owners/{owner}/payouts/{payout}/void', [\App\Http\Controllers\OwnerPayoutController::class, 'void'])
        ->whereNumber('owner')->whereNumber('payout')->name('owners.payouts.void');
    // Phase-104: re-send a payout's remittance advice (email + in-app).
    Route::post('/owners/{owner}/payouts/{payout}/advice', [\App\Http\Controllers\OwnerPayoutController::class, 'advice'])
        ->whereNumber('owner')->whereNumber('payout')->middleware('throttle:notification-send')->name('owners.payouts.advice');
});
