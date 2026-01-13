<?php

namespace App\Services;

use App\Models\LandlordPayoutAccount;
use App\Models\PlatformBillingSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaystackSubaccountService
{
    protected PaystackService $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Create a new payout account with Paystack subaccount
     *
     * @throws \Exception
     */
    public function createPayoutAccount(User $landlord, array $data): LandlordPayoutAccount
    {
        // First, verify the bank account
        $verification = $this->paystackService->resolveAccountNumber(
            $data['account_number'],
            $data['bank_code']
        );

        if (! $verification || ! $verification['status']) {
            throw new \Exception('Could not verify bank account. Please check account details.');
        }

        $billingSettings = PlatformBillingSetting::current();
        $percentageCharge = $billingSettings->transaction_fee_percentage;

        return DB::transaction(function () use ($landlord, $data, $verification, $percentageCharge) {
            // Create subaccount on Paystack
            $subaccountResponse = $this->paystackService->createSubaccount([
                'business_name' => $data['business_name'],
                'bank_code' => $data['bank_code'],
                'account_number' => $data['account_number'],
                'percentage_charge' => $percentageCharge,
                'email' => $landlord->email,
                'phone' => $landlord->mobile_number ?? null,
                'metadata' => [
                    'landlord_id' => $landlord->id,
                    'platform' => 'PropManager',
                ],
            ]);

            if (! $subaccountResponse || ! $subaccountResponse['status']) {
                $message = $subaccountResponse['message'] ?? 'Failed to create Paystack subaccount.';
                throw new \Exception($message);
            }

            $subaccountData = $subaccountResponse['data'];

            // If this is the first account, make it primary
            $isPrimary = ! LandlordPayoutAccount::where('landlord_id', $landlord->id)->exists();

            // Create local record
            return LandlordPayoutAccount::create([
                'landlord_id' => $landlord->id,
                'provider' => 'paystack',
                'subaccount_code' => $subaccountData['subaccount_code'],
                'account_type' => 'bank',
                'account_number' => $data['account_number'],
                'account_name' => $verification['data']['account_name'] ?? $data['account_name'] ?? null,
                'bank_code' => $data['bank_code'],
                'bank_name' => $data['bank_name'] ?? $subaccountData['settlement_bank'] ?? null,
                'business_name' => $data['business_name'],
                'settlement_bank' => $subaccountData['settlement_bank'] ?? null,
                'percentage_charge' => $percentageCharge,
                'verification_status' => 'verified',
                'is_active' => true,
                'is_primary' => $isPrimary,
                'verified_at' => now(),
                'metadata' => $subaccountData,
            ]);
        });
    }

    /**
     * Update subaccount fee percentage (when platform changes rates)
     */
    public function updateFeePercentage(LandlordPayoutAccount $account, float $newPercentage): bool
    {
        if (! $account->subaccount_code) {
            return false;
        }

        $response = $this->paystackService->updateSubaccount($account->subaccount_code, [
            'percentage_charge' => $newPercentage,
        ]);

        if ($response && $response['status']) {
            $account->update([
                'percentage_charge' => $newPercentage,
                'metadata' => array_merge($account->metadata ?? [], $response['data'] ?? []),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Update all subaccounts when platform fee changes
     */
    public function updateAllSubaccountFees(float $newPercentage): array
    {
        $accounts = LandlordPayoutAccount::where('provider', 'paystack')
            ->whereNotNull('subaccount_code')
            ->verified()
            ->active()
            ->get();

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($accounts as $account) {
            try {
                if ($this->updateFeePercentage($account, $newPercentage)) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to update account {$account->id}";
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Account {$account->id}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Get primary payout account for a landlord
     */
    public function getPrimaryAccount(User $landlord): ?LandlordPayoutAccount
    {
        return LandlordPayoutAccount::where('landlord_id', $landlord->id)
            ->primary()
            ->verified()
            ->active()
            ->first();
    }

    /**
     * Get any active payout account for a landlord
     */
    public function getActiveAccount(User $landlord): ?LandlordPayoutAccount
    {
        return LandlordPayoutAccount::where('landlord_id', $landlord->id)
            ->verified()
            ->active()
            ->orderByDesc('is_primary')
            ->first();
    }

    /**
     * Verify account status with Paystack
     */
    public function syncAccountStatus(LandlordPayoutAccount $account): bool
    {
        if (! $account->subaccount_code) {
            return false;
        }

        $response = $this->paystackService->getSubaccount($account->subaccount_code);

        if ($response && $response['status']) {
            $data = $response['data'];
            $account->update([
                'is_active' => $data['active'] ?? $account->is_active,
                'percentage_charge' => $data['percentage_charge'] ?? $account->percentage_charge,
                'metadata' => $data,
            ]);

            return true;
        }

        return false;
    }

    /**
     * List available banks
     */
    public function getAvailableBanks(): array
    {
        $response = $this->paystackService->listBanks('kenya');

        return $response['data'] ?? [];
    }

    /**
     * Verify a bank account number
     */
    public function verifyAccountNumber(string $accountNumber, string $bankCode): ?array
    {
        $response = $this->paystackService->resolveAccountNumber($accountNumber, $bankCode);

        if ($response && $response['status']) {
            return $response['data'];
        }

        return null;
    }

    /**
     * Check if landlord has a payout account set up
     */
    public function hasPayoutAccount(User $landlord): bool
    {
        return LandlordPayoutAccount::where('landlord_id', $landlord->id)
            ->verified()
            ->active()
            ->exists();
    }

    /**
     * Get landlord's payout accounts
     */
    public function getLandlordAccounts(User $landlord): \Illuminate\Database\Eloquent\Collection
    {
        return LandlordPayoutAccount::where('landlord_id', $landlord->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('created_at')
            ->get();
    }
}
