<?php

namespace App\Services;

use App\Events\PlatformFeeRecorded;
use App\Models\BillingModelChange;
use App\Models\LandlordPayoutAccount;
use App\Models\Payment;
use App\Models\PlatformBillingSetting;
use App\Models\PlatformFee;
use App\Models\User;
use App\Services\FeeCalculation\FeeCalculationResult;
use App\Services\FeeCalculation\FeeCalculationStrategy;
use App\Services\FeeCalculation\HybridFeeStrategy;
use App\Services\FeeCalculation\SubscriptionOnlyStrategy;
use App\Services\FeeCalculation\TransactionFeeStrategy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingModelService
{
    protected ?FeeCalculationStrategy $strategy = null;

    protected ?PaystackSubaccountService $subaccountService = null;

    public function __construct(?PaystackSubaccountService $subaccountService = null)
    {
        $this->subaccountService = $subaccountService;
    }

    /**
     * Get the currently active billing model settings
     */
    public function getActiveBillingModel(): PlatformBillingSetting
    {
        return PlatformBillingSetting::current();
    }

    /**
     * Get the appropriate fee calculation strategy based on active billing model
     */
    public function getStrategy(): FeeCalculationStrategy
    {
        if ($this->strategy) {
            return $this->strategy;
        }

        $settings = $this->getActiveBillingModel();

        return $this->strategy = match ($settings->active_billing_model) {
            'transaction_fee' => new TransactionFeeStrategy($settings),
            'subscription' => new SubscriptionOnlyStrategy,
            'hybrid' => new HybridFeeStrategy($settings),
            default => new TransactionFeeStrategy($settings),
        };
    }

    /**
     * Reset strategy cache (call after settings change)
     */
    public function resetStrategy(): void
    {
        $this->strategy = null;
    }

    /**
     * Calculate platform fee for a payment
     */
    public function calculatePlatformFee(float $amount, User $landlord): FeeCalculationResult
    {
        return $this->getStrategy()->calculateFee($amount, $landlord);
    }

    /**
     * Record platform fee after successful payment
     */
    public function recordPlatformFee(
        Payment $payment,
        FeeCalculationResult $feeResult,
        ?LandlordPayoutAccount $payoutAccount = null,
        ?string $splitReference = null,
        ?array $splitDetails = null
    ): PlatformFee {
        $platformFee = PlatformFee::create([
            'payment_id' => $payment->id,
            'landlord_id' => $payment->landlord_id,
            'payout_account_id' => $payoutAccount?->id,
            'gross_amount' => $feeResult->grossAmount,
            'fee_amount' => $feeResult->feeAmount,
            'net_amount' => $feeResult->netAmount,
            'fee_type' => $feeResult->feeType,
            'fee_percentage_applied' => $feeResult->percentageApplied,
            'status' => $splitReference ? 'collected' : 'pending',
            'paystack_split_reference' => $splitReference,
            'split_details' => $splitDetails,
            'collected_at' => $splitReference ? now() : null,
        ]);

        // Dispatch event for potential listeners (notifications, analytics)
        event(new PlatformFeeRecorded($platformFee));

        Log::info('Platform fee recorded', [
            'payment_id' => $payment->id,
            'fee_amount' => $feeResult->feeAmount,
            'fee_type' => $feeResult->feeType,
        ]);

        return $platformFee;
    }

    /**
     * Switch billing model (admin action)
     */
    public function switchBillingModel(
        string $newModel,
        User $changedBy,
        ?string $reason = null,
        ?\DateTime $effectiveDate = null
    ): PlatformBillingSetting {
        return DB::transaction(function () use ($newModel, $changedBy, $reason, $effectiveDate) {
            $currentSettings = $this->getActiveBillingModel();
            $oldModel = $currentSettings->active_billing_model;

            // Skip if no change
            if ($oldModel === $newModel) {
                return $currentSettings;
            }

            // Create audit log
            BillingModelChange::create([
                'from_model' => $oldModel,
                'to_model' => $newModel,
                'changed_by' => $changedBy->id,
                'effective_date' => $effectiveDate ?? now(),
                'reason' => $reason,
                'settings_snapshot' => $currentSettings->toArray(),
            ]);

            // Update settings
            $currentSettings->update([
                'active_billing_model' => $newModel,
                'updated_by' => $changedBy->id,
            ]);

            // Reset strategy cache
            $this->resetStrategy();

            Log::info('Billing model changed', [
                'from' => $oldModel,
                'to' => $newModel,
                'by' => $changedBy->id,
                'reason' => $reason,
            ]);

            return $currentSettings->fresh();
        });
    }

    /**
     * Update fee percentage (admin action)
     */
    public function updateFeePercentage(float $newPercentage, User $changedBy, ?string $reason = null): PlatformBillingSetting
    {
        return DB::transaction(function () use ($newPercentage, $changedBy, $reason) {
            $settings = $this->getActiveBillingModel();
            $oldPercentage = $settings->transaction_fee_percentage;

            // Skip if no change
            if ($oldPercentage == $newPercentage) {
                return $settings;
            }

            // Create audit record
            BillingModelChange::create([
                'from_model' => $settings->active_billing_model,
                'to_model' => $settings->active_billing_model,
                'changed_by' => $changedBy->id,
                'effective_date' => now(),
                'reason' => $reason ?? "Fee percentage changed from {$oldPercentage}% to {$newPercentage}%",
                'settings_snapshot' => $settings->toArray(),
            ]);

            $settings->update([
                'transaction_fee_percentage' => $newPercentage,
                'updated_by' => $changedBy->id,
            ]);

            // Reset strategy cache
            $this->resetStrategy();

            // Update all Paystack subaccounts with new fee percentage
            if ($this->subaccountService) {
                $this->subaccountService->updateAllSubaccountFees($newPercentage);
            }

            Log::info('Transaction fee percentage updated', [
                'from' => $oldPercentage,
                'to' => $newPercentage,
                'by' => $changedBy->id,
            ]);

            return $settings->fresh();
        });
    }

    /**
     * Update billing settings (admin action)
     */
    public function updateSettings(array $data, User $changedBy): PlatformBillingSetting
    {
        return DB::transaction(function () use ($data, $changedBy) {
            $settings = $this->getActiveBillingModel();

            // Create audit record
            BillingModelChange::create([
                'from_model' => $settings->active_billing_model,
                'to_model' => $data['active_billing_model'] ?? $settings->active_billing_model,
                'changed_by' => $changedBy->id,
                'effective_date' => now(),
                'reason' => $data['reason'] ?? 'Settings updated',
                'settings_snapshot' => $settings->toArray(),
            ]);

            $settings->update(array_merge($data, [
                'updated_by' => $changedBy->id,
            ]));

            // Reset strategy cache
            $this->resetStrategy();

            // Update Paystack subaccounts if fee percentage changed
            if (isset($data['transaction_fee_percentage']) && $this->subaccountService) {
                $this->subaccountService->updateAllSubaccountFees($data['transaction_fee_percentage']);
            }

            return $settings->fresh();
        });
    }

    /**
     * Check if split payments are available for a landlord
     */
    public function canUseSplitPayments(User $landlord): bool
    {
        $settings = $this->getActiveBillingModel();

        // Subscription-only model doesn't use split payments
        if ($settings->isSubscriptionModel()) {
            return false;
        }

        // Check if landlord has a verified payout account
        return LandlordPayoutAccount::where('landlord_id', $landlord->id)
            ->verified()
            ->active()
            ->exists();
    }

    /**
     * Check if landlord needs to connect a payout account before accepting payments
     */
    public function requiresPayoutAccount(User $landlord): bool
    {
        $settings = $this->getActiveBillingModel();

        // Subscription model doesn't require payout account for split payments
        if ($settings->isSubscriptionModel()) {
            return false;
        }

        // Check if landlord already has a payout account
        return ! LandlordPayoutAccount::where('landlord_id', $landlord->id)
            ->verified()
            ->active()
            ->exists();
    }

    /**
     * Get split payment configuration for a transaction
     */
    public function getSplitPaymentConfig(User $landlord, float $amount): ?array
    {
        if (! $this->canUseSplitPayments($landlord)) {
            return null;
        }

        $payoutAccount = LandlordPayoutAccount::where('landlord_id', $landlord->id)
            ->primary()
            ->verified()
            ->active()
            ->first();

        if (! $payoutAccount) {
            // Try to get any active account
            $payoutAccount = LandlordPayoutAccount::where('landlord_id', $landlord->id)
                ->verified()
                ->active()
                ->first();
        }

        if (! $payoutAccount) {
            return null;
        }

        return [
            'subaccount_code' => $payoutAccount->subaccount_code,
            'bearer' => 'subaccount', // Landlord receives (100 - fee%), platform gets fee%
            'payout_account' => $payoutAccount,
        ];
    }

    /**
     * Get billing change history
     */
    public function getChangeHistory(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return BillingModelChange::with('changedByUser')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get platform revenue analytics for a period
     */
    public function getRevenueAnalytics(\DateTime $startDate, \DateTime $endDate): array
    {
        $fees = PlatformFee::whereIn('status', ['collected', 'settled'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalGross = (clone $fees)->sum('gross_amount');
        $totalFees = (clone $fees)->sum('fee_amount');
        $totalNet = (clone $fees)->sum('net_amount');
        $transactionCount = (clone $fees)->count();
        $avgFeePercentage = (clone $fees)->avg('fee_percentage_applied');

        // Daily breakdown
        $dailyFees = PlatformFee::whereIn('status', ['collected', 'settled'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(fee_amount) as total_fees, COUNT(*) as transactions')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'totals' => [
                'gross_amount' => round($totalGross, 2),
                'platform_fees' => round($totalFees, 2),
                'landlord_net' => round($totalNet, 2),
                'transaction_count' => $transactionCount,
                'average_fee_percentage' => round($avgFeePercentage ?? 0, 2),
            ],
            'daily_breakdown' => $dailyFees,
        ];
    }
}
