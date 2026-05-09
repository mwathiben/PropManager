<?php

namespace App\Http\Controllers;

use App\Models\LandlordPayoutAccount;
use App\Models\PlatformBillingSetting;
use App\Services\PaystackSubaccountService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandlordPayoutAccountController extends Controller
{
    protected PaystackSubaccountService $subaccountService;

    public function __construct(PaystackSubaccountService $subaccountService)
    {
        $this->subaccountService = $subaccountService;
    }

    /**
     * Display payout accounts for the authenticated landlord
     */
    public function index(): Response
    {
        $this->authorize('viewAny', LandlordPayoutAccount::class);

        $user = auth()->user();
        $accounts = $this->subaccountService->getLandlordAccounts($user);
        $billingSettings = PlatformBillingSetting::current();

        return Inertia::render('Settings/PayoutAccounts', [
            'accounts' => $accounts->map(function ($account) {
                return [
                    'id' => $account->id,
                    'provider' => $account->provider,
                    'provider_label' => $account->provider_label,
                    'account_type' => $account->account_type,
                    'account_type_label' => $account->account_type_label,
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
            }),
            'hasPrimaryAccount' => $accounts->where('is_primary', true)->isNotEmpty(),
            'hasVerifiedAccount' => $accounts->where('verification_status', 'verified')->isNotEmpty(),
            'currentFeePercentage' => $billingSettings->transaction_fee_percentage,
            'billingModel' => $billingSettings->active_billing_model,
        ]);
    }

    /**
     * Get list of available banks
     */
    public function banks(): \Illuminate\Http\JsonResponse
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
    public function verifyAccount(Request $request): \Illuminate\Http\JsonResponse
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
                'account_name' => $result['account_name'],
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Could not verify account. Please check the details.',
        ], 400);
    }

    /**
     * Store a new payout account
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('create', LandlordPayoutAccount::class);

        $user = auth()->user();
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
    public function setPrimary(LandlordPayoutAccount $account): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setPrimary', $account);

        if (! $account->canReceivePayments()) {
            return redirect()->back()->withErrors(['error' => 'Account must be verified and active to be set as primary.']);
        }

        $account->setAsPrimary();

        return redirect()->back()->with('success', 'Primary account updated successfully.');
    }

    /**
     * Deactivate a payout account
     */
    public function destroy(LandlordPayoutAccount $account): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $account);

        if ($account->is_primary) {
            return redirect()->back()->withErrors(['error' => 'Cannot deactivate primary account. Set another account as primary first.']);
        }

        // We don't delete, just deactivate
        $account->update(['is_active' => false]);

        return redirect()->back()->with('success', 'Payout account deactivated successfully.');
    }

    /**
     * Sync account status with Paystack
     */
    public function sync(LandlordPayoutAccount $account): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('sync', $account);

        $synced = $this->subaccountService->syncAccountStatus($account);

        if ($synced) {
            return redirect()->back()->with('success', 'Account status synced successfully.');
        }

        return redirect()->back()->withErrors(['error' => 'Failed to sync account status.']);
    }
}
