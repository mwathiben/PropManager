<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformBillingSetting extends Model
{
    protected $fillable = [
        'active_billing_model',
        'transaction_fee_percentage',
        'minimum_fee',
        'maximum_fee',
        'fee_bearer',
        'hybrid_subscription_discount',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'transaction_fee_percentage' => 'decimal:2',
        'minimum_fee' => 'decimal:2',
        'maximum_fee' => 'decimal:2',
        'hybrid_subscription_discount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Billing model types
     */
    const BILLING_MODELS = [
        'transaction_fee' => 'Transaction Fee',
        'subscription' => 'Subscription Only',
        'hybrid' => 'Hybrid (Subscription + Reduced Fees)',
    ];

    /**
     * Fee bearer options
     */
    const FEE_BEARERS = [
        'landlord' => 'Landlord (deducted from their share)',
        'platform' => 'Platform (absorbed by platform)',
        'shared' => 'Shared (split between both)',
    ];

    /**
     * Get the user who last updated the settings
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the current active billing settings (singleton pattern)
     */
    public static function current(): self
    {
        return self::where('is_active', true)->first()
            ?? self::createDefault();
    }

    /**
     * Create default billing settings
     */
    public static function createDefault(): self
    {
        return self::create([
            'active_billing_model' => 'transaction_fee',
            'transaction_fee_percentage' => env('DEFAULT_TRANSACTION_FEE_PERCENTAGE', 2.50),
            'minimum_fee' => env('DEFAULT_MINIMUM_FEE', 50.00),
            'maximum_fee' => null,
            'fee_bearer' => 'landlord',
            'hybrid_subscription_discount' => 100.00,
            'is_active' => true,
        ]);
    }

    /**
     * Check if transaction fee model is active
     */
    public function isTransactionFeeModel(): bool
    {
        return $this->active_billing_model === 'transaction_fee';
    }

    /**
     * Check if subscription model is active
     */
    public function isSubscriptionModel(): bool
    {
        return $this->active_billing_model === 'subscription';
    }

    /**
     * Check if hybrid model is active
     */
    public function isHybridModel(): bool
    {
        return $this->active_billing_model === 'hybrid';
    }

    /**
     * Get the billing model label
     */
    public function getBillingModelLabelAttribute(): string
    {
        return self::BILLING_MODELS[$this->active_billing_model] ?? $this->active_billing_model;
    }

    /**
     * Get the fee bearer label
     */
    public function getFeeBearerLabelAttribute(): string
    {
        return self::FEE_BEARERS[$this->fee_bearer] ?? $this->fee_bearer;
    }

    /**
     * Calculate fee for a given amount (quick preview method)
     */
    public function calculateFeePreview(float $amount): array
    {
        if ($this->isSubscriptionModel()) {
            return [
                'gross_amount' => $amount,
                'fee_amount' => 0,
                'net_amount' => $amount,
                'fee_percentage' => 0,
            ];
        }

        $calculatedFee = ($amount * $this->transaction_fee_percentage) / 100;
        $fee = max($calculatedFee, $this->minimum_fee);

        if ($this->maximum_fee) {
            $fee = min($fee, $this->maximum_fee);
        }

        $fee = min($fee, $amount);

        return [
            'gross_amount' => $amount,
            'fee_amount' => round($fee, 2),
            'net_amount' => round($amount - $fee, 2),
            'fee_percentage' => $this->transaction_fee_percentage,
        ];
    }
}
