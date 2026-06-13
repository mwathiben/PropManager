<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\Settings\UpdatePaymentMethodsRequest;
use App\Http\Traits\WithLandlordScope;
use App\Models\Invoice;
use App\Models\LandlordPayoutAccount;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\PlatformBillingSetting;
use App\Models\PlatformFee;
use App\Services\BillingModelService;
use App\Services\FinanceCacheService;
use App\Services\PaystackSubaccountService;
use App\Services\SecurityLogger;
use App\Services\Settings\PaymentMethodConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PaymentsHubController extends Controller
{
    use WithLandlordScope;

    public function __construct(
        protected BillingModelService $billingService,
        protected PaystackSubaccountService $subaccountService,
        protected PaymentMethodConfigService $configService
    ) {}

    // ========================================
    // TAB ROUTES
    // ========================================

    /**
     * Redirect to overview tab
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('payments-hub.overview');
    }

    /**
     * Overview tab - dashboard with stats and quick actions
     */
    public function overview(): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderHub('overview', [
            'stats' => $this->getPaymentStats($landlordId),
            'recentPayments' => $this->getRecentPayments($landlordId, 5),
            'pendingInvoices' => $this->getPendingInvoicesCount($landlordId),
            'collectionStatus' => $this->getCollectionStatus($landlordId),
            'payoutAccountSummary' => $this->getPayoutAccountSummary($landlordId),
            'quickActions' => $this->getQuickActions(),
        ]);
    }

    /**
     * Collection tab - payment methods, gateway credentials, and payout accounts.
     *
     * Passes the full masked credential config (including *_last4 fields) so
     * the Collection tab can render the complete gateway credentials form.
     */
    public function collection(): Response
    {
        $landlordId = $this->getLandlordId();

        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $this->renderHub('collection', [
            'paymentMethods' => PaymentConfiguration::getAvailablePaymentMethods(),
            'paymentConfig' => $this->configService->maskedConfig($user),
            'payoutAccounts' => $this->getPayoutAccounts($landlordId),
            'billingSettings' => $this->getBillingSettings(),
        ]);
    }

    /**
     * Analytics tab - collection-specific insights
     */
    public function analytics(Request $request): Response
    {
        $landlordId = $this->getLandlordId();
        $period = $request->get('period', 'month');

        return $this->renderHub('analytics', [
            'period' => $period,
            'collectionRates' => $this->getCollectionRates($landlordId),
            'paymentMethodBreakdown' => $this->getPaymentMethodBreakdown($landlordId),
            'platformFees' => $this->getPlatformFeeSummary($landlordId),
        ]);
    }

    /**
     * Settings tab - payment preferences
     */
    public function settings(): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderHub('settings', [
            'preferences' => $this->getPaymentPreferences($landlordId),
            'invoiceSettings' => $this->getInvoiceSettings($landlordId),
            'reminderSettings' => $this->getReminderSettings($landlordId),
        ]);
    }

    // ========================================
    // ACTION ROUTES
    // ========================================

    /**
     * Update payment methods and gateway credentials from the Collection tab.
     *
     * Uses the canonical UpdatePaymentMethodsRequest (full validation rules) and
     * delegates to PaymentMethodConfigService for blank-preserve + audit logic.
     */
    public function updatePaymentMethods(UpdatePaymentMethodsRequest $request, SecurityLogger $logger): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $this->configService->apply($user, $request->validated(), $logger);

        return redirect()->back()->with('success', 'Payment methods updated successfully.');
    }

    /**
     * Store a new payout account
     */
    public function storePayoutAccount(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isScopeOwner()) {
            abort(403, 'Only landlords can add payout accounts.');
        }

        $request->validate([
            'business_name' => 'required|string|max:255',
            'bank_code' => 'required|string',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:20',
            'account_name' => 'nullable|string|max:255',
        ]);

        try {
            $this->subaccountService->createPayoutAccount(
                $user,
                $request->only([
                    'business_name',
                    'bank_code',
                    'bank_name',
                    'account_number',
                    'account_name',
                ])
            );

            return redirect()->back()->with('success', 'Payout account created and verified successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Set a payout account as primary
     */
    public function setPayoutPrimary(LandlordPayoutAccount $account): RedirectResponse
    {
        if ($account->landlord_id !== $this->getLandlordId()) {
            abort(403, 'You can only manage your own payout accounts.');
        }

        if (! $account->canReceivePayments()) {
            return redirect()->back()->withErrors(['error' => 'Account must be verified and active to be set as primary.']);
        }

        $account->setAsPrimary();

        return redirect()->back()->with('success', 'Primary account updated successfully.');
    }

    /**
     * Deactivate a payout account
     */
    public function destroyPayoutAccount(LandlordPayoutAccount $account): RedirectResponse
    {
        if ($account->landlord_id !== $this->getLandlordId()) {
            abort(403, 'You can only manage your own payout accounts.');
        }

        if ($account->is_primary) {
            return redirect()->back()->withErrors(['error' => 'Cannot deactivate primary account. Set another account as primary first.']);
        }

        $account->update(['is_active' => false]);

        return redirect()->back()->with('success', 'Payout account deactivated successfully.');
    }

    /**
     * Sync account status with Paystack
     */
    public function syncPayoutAccount(LandlordPayoutAccount $account): RedirectResponse
    {
        if ($account->landlord_id !== $this->getLandlordId()) {
            abort(403, 'You can only manage your own payout accounts.');
        }

        $synced = $this->subaccountService->syncAccountStatus($account);

        if ($synced) {
            return redirect()->back()->with('success', 'Account status synced successfully.');
        }

        return redirect()->back()->withErrors(['error' => 'Failed to sync account status.']);
    }

    /**
     * Mark payment setup as complete
     */
    public function completeSetup(): RedirectResponse
    {
        return redirect()->route('payments-hub.overview')->with('success', 'Payment setup completed successfully!');
    }

    /**
     * Update payment preferences
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        $validated = $request->validate([
            'default_payment_terms_days' => 'nullable|integer|min:1|max:90',
            'auto_send_invoices' => 'boolean',
            'invoice_footer' => 'nullable|string|max:500',
            'reminder_days_before_due' => 'nullable|integer|min:1|max:30',
            'overdue_reminder_frequency' => 'nullable|in:daily,weekly,none',
        ]);

        // Store preferences in PaymentConfiguration or a dedicated preferences table
        $config = PaymentConfiguration::getOrCreateForLandlord($landlordId);

        // For now, we'll store basic settings. You may want to extend the model.
        // This is a placeholder for preference storage.

        return redirect()->back()->with('success', 'Payment preferences updated successfully.');
    }

    // ========================================
    // AJAX API ROUTES
    // ========================================

    /**
     * Get list of available banks
     */
    public function getBanks(): JsonResponse
    {
        $banks = $this->subaccountService->getAvailableBanks();

        return response()->json([
            'status' => 'success',
            'banks' => $banks,
        ]);
    }

    /**
     * Verify a bank account number
     */
    public function verifyAccount(Request $request): JsonResponse
    {
        $request->validate([
            'account_number' => 'required|string|max:20',
            'bank_code' => 'required|string',
        ]);

        $result = $this->subaccountService->verifyAccountNumber(
            $request->account_number,
            $request->bank_code
        );

        if ($result) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'account_name' => $result['account_name'],
            ]);
        }

        return response()->json([
            'status' => 'error',
            'success' => false,
            'message' => 'Could not verify account. Please check the details.',
        ], 400);
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Render the hub with common props
     */
    private function renderHub(string $tab, array $additionalProps = []): Response
    {
        $landlordId = $this->getLandlordId();
        $setupData = $this->getSetupData($landlordId);

        $baseProps = [
            'activeTab' => $tab,
            'setupComplete' => $setupData['setupComplete'],
            'setupProgress' => $setupData['setupProgress'],
            'tabs' => $this->getTabsConfig(),
        ];

        return Inertia::render('PaymentsHub/Index', array_merge($baseProps, $additionalProps));
    }

    /**
     * Get consolidated setup data (completion status and progress).
     *
     * OPT-016: Consolidates isSetupComplete() and getSetupProgress() to avoid
     * duplicate queries. Defers expensive queries until actually needed.
     */
    private function getSetupData(int $landlordId): array
    {
        $paymentConfig = PaymentConfiguration::where('landlord_id', $landlordId)->first();
        $hasPaymentMethods = $paymentConfig && count($paymentConfig->accepted_payment_methods ?? []) > 0;

        if (! $hasPaymentMethods) {
            return [
                'setupComplete' => false,
                'setupProgress' => [
                    'payment_methods' => false,
                    'payout_account' => false,
                    'first_payment' => false,
                ],
            ];
        }

        $acceptsOnline = in_array('paystack', $paymentConfig->accepted_payment_methods ?? []);

        $hasVerifiedPayout = $acceptsOnline
            ? LandlordPayoutAccount::where('landlord_id', $landlordId)
                ->verified()
                ->active()
                ->exists()
            : false;

        $hasFirstPayment = Payment::where('landlord_id', $landlordId)->exists();

        $isComplete = $hasPaymentMethods && (! $acceptsOnline || $hasVerifiedPayout);

        return [
            'setupComplete' => $isComplete,
            'setupProgress' => [
                'payment_methods' => true,
                'payout_account' => $hasVerifiedPayout,
                'first_payment' => $hasFirstPayment,
            ],
        ];
    }

    /**
     * Get tabs configuration
     */
    private function getTabsConfig(): array
    {
        return [
            ['id' => 'overview', 'name' => 'Overview', 'route' => 'payments-hub.overview', 'icon' => 'HomeIcon'],
            ['id' => 'collection', 'name' => 'Collection', 'route' => 'payments-hub.collection', 'icon' => 'CreditCardIcon'],
            ['id' => 'analytics', 'name' => 'Analytics', 'route' => 'payments-hub.analytics', 'icon' => 'ChartBarIcon'],
            ['id' => 'settings', 'name' => 'Settings', 'route' => 'payments-hub.settings', 'icon' => 'Cog6ToothIcon'],
        ];
    }

    /**
     * Get payment statistics
     */
    // PERF-Q9: cache the whole stats payload at the same TTL as other
    // finance stats so repeat overview tab loads inside the 5-min window
    // hit memory instead of 6 aggregate queries.
    private function getPaymentStats(int $landlordId): array
    {
        return FinanceCacheService::rememberStats(
            'payments_hub_overview',
            $landlordId,
            fn () => $this->computePaymentStats($landlordId),
            now()->format('Y-m'),
        );
    }

    private function computePaymentStats(int $landlordId): array
    {
        $now = now();

        $totalCollected = Payment::withArchived()->where('landlord_id', $landlordId)->sum('amount');

        // Combined: thisMonth (sum) + paymentCount (count) for the same
        // predicate — single scan instead of two.
        $monthAgg = Payment::where('landlord_id', $landlordId)
            ->whereMonth('payment_date', $now->month)
            ->whereYear('payment_date', $now->year)
            ->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(*) as cnt')
            ->first();

        $pendingAmount = Invoice::where('landlord_id', $landlordId)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue])
            ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as pending')
            ->value('pending') ?? 0;

        return [
            'total_collected' => round((float) $totalCollected, 2),
            'this_month' => round((float) ($monthAgg->total ?? 0), 2),
            'pending_amount' => round((float) $pendingAmount, 2),
            'payment_count' => (int) ($monthAgg->cnt ?? 0),
            'collection_rate' => $this->calculateCollectionRate($landlordId),
        ];
    }

    /**
     * Calculate collection rate for current month
     */
    private function calculateCollectionRate(int $landlordId): float
    {
        $now = now();

        // PERF-Q9: combined SUMs — one scan instead of two on the same
        // landlord+month predicate.
        $totals = Invoice::where('landlord_id', $landlordId)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->selectRaw('COALESCE(SUM(total_due), 0) as invoiced, COALESCE(SUM(amount_paid), 0) as collected')
            ->first();

        $invoiced = (float) ($totals->invoiced ?? 0);

        if ($invoiced <= 0) {
            return 0;
        }

        return round(((float) ($totals->collected ?? 0) / $invoiced) * 100, 1);
    }

    /**
     * Get recent payments
     */
    private function getRecentPayments(int $landlordId, int $limit = 5): array
    {
        return Payment::where('landlord_id', $landlordId)
            ->with([
                'lease.tenant:id,name',
                'lease.unit:id,unit_number,building_id',
                'lease.unit.building:id,name',
            ])
            ->orderBy('payment_date', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_date' => $payment->payment_date->format('M d, Y'),
                    'tenant_name' => $payment->lease?->tenant?->name ?? 'Unknown',
                    'unit' => $payment->lease?->unit?->unit_number ?? 'N/A',
                    'building' => $payment->lease?->unit?->building?->name ?? 'N/A',
                    'reference' => $payment->reference,
                ];
            })
            ->toArray();
    }

    /**
     * Get count of pending invoices
     */
    private function getPendingInvoicesCount(int $landlordId): int
    {
        return Invoice::where('landlord_id', $landlordId)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue])
            ->count();
    }

    /**
     * Get collection status (for dashboard indicator)
     */
    private function getCollectionStatus(int $landlordId): string
    {
        $rate = $this->calculateCollectionRate($landlordId);

        if ($rate >= 90) {
            return 'excellent';
        } elseif ($rate >= 75) {
            return 'good';
        } elseif ($rate >= 50) {
            return 'needs_attention';
        } else {
            return 'critical';
        }
    }

    /**
     * Get payout account summary
     */
    private function getPayoutAccountSummary(int $landlordId): array
    {
        $accounts = LandlordPayoutAccount::where('landlord_id', $landlordId)
            ->active()
            ->get();

        $primaryAccount = $accounts->where('is_primary', true)->first();

        return [
            'has_accounts' => $accounts->isNotEmpty(),
            'has_verified' => $accounts->where('verification_status', 'verified')->isNotEmpty(),
            'primary_account' => $primaryAccount ? [
                'id' => $primaryAccount->id,
                'bank_name' => $primaryAccount->bank_name,
                'masked_account_number' => $primaryAccount->masked_account_number,
                'status' => $primaryAccount->verification_status,
            ] : null,
            'account_count' => $accounts->count(),
        ];
    }

    /**
     * Get quick actions for overview tab
     */
    private function getQuickActions(): array
    {
        return [
            [
                'id' => 'record_payment',
                'label' => 'Record Payment',
                'description' => 'Manually record a cash or bank payment',
                'route' => 'invoices.index',
                'icon' => 'BanknotesIcon',
            ],
            [
                'id' => 'generate_invoices',
                'label' => 'Generate Invoices',
                'description' => 'Create invoices for the current month',
                'route' => 'invoices.generate',
                'icon' => 'DocumentTextIcon',
            ],
            [
                'id' => 'send_reminders',
                'label' => 'Send Reminders',
                'description' => 'Send payment reminders to tenants',
                'route' => 'notifications.index',
                'icon' => 'BellIcon',
            ],
        ];
    }

    /**
     * Get payout accounts for collection tab
     */
    private function getPayoutAccounts(int $landlordId): array
    {
        return LandlordPayoutAccount::where('landlord_id', $landlordId)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'provider' => $account->provider,
                    'provider_label' => $account->provider_label,
                    'account_type' => $account->account_type,
                    'account_name' => $account->account_name,
                    'masked_account_number' => $account->masked_account_number,
                    'bank_name' => $account->bank_name,
                    'business_name' => $account->business_name,
                    'verification_status' => $account->verification_status,
                    'status_label' => $account->status_label,
                    'status_color' => $account->status_color,
                    'is_primary' => $account->is_primary,
                    'is_active' => $account->is_active,
                    'can_receive_payments' => $account->canReceivePayments(),
                    'created_at' => $account->created_at->format('M d, Y'),
                ];
            })
            ->toArray();
    }

    /**
     * Get billing settings
     */
    private function getBillingSettings(): array
    {
        $settings = PlatformBillingSetting::current();

        return [
            'transaction_fee_percentage' => $settings->transaction_fee_percentage,
            'minimum_fee' => $settings->minimum_fee,
            'billing_model' => $settings->active_billing_model,
        ];
    }

    /**
     * Get collection rates
     */
    private function getCollectionRates(int $landlordId): array
    {
        $currentMonth = $this->calculateCollectionRate($landlordId);

        // Calculate previous month's rate in a single query
        $previousMonth = now()->subMonth();
        $previousStats = Invoice::where('landlord_id', $landlordId)
            ->whereMonth('created_at', $previousMonth->month)
            ->whereYear('created_at', $previousMonth->year)
            ->selectRaw('COALESCE(SUM(total_due), 0) as invoiced, COALESCE(SUM(amount_paid), 0) as collected')
            ->first();

        $previousInvoiced = (float) $previousStats->invoiced;
        $previousCollected = (float) $previousStats->collected;

        $previousRate = $previousInvoiced > 0
            ? round(($previousCollected / $previousInvoiced) * 100, 1)
            : 0;

        return [
            'current_month' => $currentMonth,
            'previous_month' => $previousRate,
            'trend' => $currentMonth >= $previousRate ? 'up' : 'down',
        ];
    }

    /**
     * Get payment method breakdown
     */
    private function getPaymentMethodBreakdown(int $landlordId): array
    {
        $now = now();

        return Payment::where('landlord_id', $landlordId)
            ->whereMonth('payment_date', $now->month)
            ->whereYear('payment_date', $now->year)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'method' => $item->payment_method,
                    'label' => PaymentConfiguration::getAvailablePaymentMethods()[$item->payment_method] ?? ucfirst($item->payment_method),
                    'count' => $item->count,
                    'total' => round($item->total, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get platform fee summary
     */
    private function getPlatformFeeSummary(int $landlordId): array
    {
        $now = now();

        $thisMonth = PlatformFee::where('landlord_id', $landlordId)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('fee_amount');

        $totalFees = PlatformFee::where('landlord_id', $landlordId)->sum('fee_amount');
        $totalGross = PlatformFee::where('landlord_id', $landlordId)->sum('gross_amount');
        $totalNet = PlatformFee::where('landlord_id', $landlordId)->sum('net_amount');

        return [
            'this_month' => round($thisMonth, 2),
            'total_fees' => round($totalFees, 2),
            'total_gross' => round($totalGross, 2),
            'total_net' => round($totalNet, 2),
        ];
    }

    /**
     * Get payment preferences
     */
    private function getPaymentPreferences(int $landlordId): array
    {
        // Placeholder - extend as needed
        return [
            'default_payment_terms_days' => 7,
            'auto_send_invoices' => true,
            'invoice_footer' => '',
        ];
    }

    /**
     * Get invoice settings
     */
    private function getInvoiceSettings(int $landlordId): array
    {
        // Placeholder - extend as needed
        return [
            'include_water_charges' => true,
            'include_arrears' => true,
            'auto_generate_monthly' => false,
        ];
    }

    /**
     * Get reminder settings
     */
    private function getReminderSettings(int $landlordId): array
    {
        // Placeholder - extend as needed
        return [
            'reminder_days_before_due' => 3,
            'overdue_reminder_frequency' => 'weekly',
            'reminder_channels' => ['email', 'sms'],
        ];
    }
}
