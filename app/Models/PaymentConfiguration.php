<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentConfiguration extends Model
{
    use TenantScope;

    protected $fillable = [
        'landlord_id',
        'default_rent',
        'water_billing_type',
        'flat_water_rate',
        'water_unit_rate',
        'accepted_payment_methods',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_branch',
        'mpesa_account_name',
        'mpesa_shortcode_type',
        'mpesa_shortcode',
        'mpesa_passkey',
        'paystack_enabled',
    ];

    protected $casts = [
        'default_rent' => 'decimal:2',
        'flat_water_rate' => 'decimal:2',
        'water_unit_rate' => 'decimal:2',
        'accepted_payment_methods' => 'array',
        'paystack_enabled' => 'boolean',
        'mpesa_passkey' => 'encrypted',
    ];

    public static function getAvailablePaymentMethods(): array
    {
        return PaymentMethod::labelsMap();
    }

    public static function getPaymentMethodOptions(): array
    {
        return PaymentMethod::options();
    }

    /**
     * Water billing types
     */
    const WATER_BILLING_TYPES = [
        'consumption' => 'Based on Consumption',
        'flat_rate' => 'Flat Rate',
        'none' => 'No Water Billing',
    ];

    /**
     * M-Pesa shortcode types
     */
    const MPESA_SHORTCODE_TYPES = [
        'paybill' => 'Paybill',
        'till' => 'Till (Buy Goods)',
    ];

    /**
     * Get the landlord
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Check if a payment method is accepted
     */
    public function acceptsPaymentMethod(string $method): bool
    {
        $normalizedMethod = PaymentMethod::normalize($method);

        return in_array($normalizedMethod, $this->accepted_payment_methods ?? []);
    }

    /**
     * Check if bank details are configured
     */
    public function hasBankDetails(): bool
    {
        return ! empty($this->bank_name) && ! empty($this->bank_account_number);
    }

    /**
     * Check if M-Pesa shortcode is configured (basic details)
     */
    public function hasMpesaDetails(): bool
    {
        return ! empty($this->mpesa_shortcode);
    }

    /**
     * Check if M-Pesa STK Push is fully configured (shortcode + passkey)
     */
    public function hasMpesaSTKConfig(): bool
    {
        return ! empty($this->mpesa_shortcode) && ! empty($this->mpesa_passkey);
    }

    /**
     * Get the M-Pesa command ID based on shortcode type
     */
    public function getMpesaCommandId(): string
    {
        return $this->mpesa_shortcode_type === 'till'
            ? 'CustomerBuyGoodsOnline'
            : 'CustomerPayBillOnline';
    }

    /**
     * Check if using Till shortcode type
     */
    public function usesTillNumber(): bool
    {
        return $this->mpesa_shortcode_type === 'till';
    }

    /**
     * Get the water rate based on billing type
     */
    public function getWaterRate(): float
    {
        if ($this->water_billing_type === 'flat_rate') {
            return $this->flat_water_rate ?? 0;
        }

        return $this->water_unit_rate ?? (float) config('propmanager.water.default_rate', 150);
    }

    /**
     * Check if water billing is enabled
     */
    public function hasWaterBilling(): bool
    {
        return $this->water_billing_type !== 'none';
    }

    /**
     * Create or get payment configuration for a landlord
     */
    public static function getOrCreateForLandlord(int $landlordId): self
    {
        return self::firstOrCreate(
            ['landlord_id' => $landlordId],
            [
                'water_billing_type' => 'consumption',
                'water_unit_rate' => config('propmanager.water.default_rate', 150),
                'accepted_payment_methods' => ['cash', 'mobile_money'],
                'paystack_enabled' => false,
            ]
        );
    }
}
