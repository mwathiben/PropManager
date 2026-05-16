<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentConfiguration extends Model
{
    use HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'default_rent',
        'water_billing_type',
        'flat_water_rate',
        'water_unit_rate',
        'accepted_payment_methods',
        'default_currency',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_branch',
        'coop_webhook_secret',
        'equity_webhook_secret',
        'kcb_webhook_secret',
        'mpesa_account_name',
        'mpesa_shortcode_type',
        'mpesa_shortcode',
        'mpesa_passkey',
        'mpesa_consumer_key',
        'mpesa_consumer_secret',
        'mpesa_b2c_shortcode',
        'mpesa_b2c_initiator',
        'mpesa_b2c_password',
        'mpesa_b2c_security_credential',
        'mpesa_environment',
        'paystack_enabled',
        'paystack_public_key',
        'paystack_secret_key',
        'intasend_enabled',
        'intasend_publishable_key',
        'intasend_secret_key',
        'intasend_webhook_challenge',
        'intasend_environment',
    ];

    protected $casts = [
        'default_rent' => 'decimal:2',
        'flat_water_rate' => 'decimal:2',
        'water_unit_rate' => 'decimal:2',
        'accepted_payment_methods' => 'array',
        'default_currency' => Currency::class,
        'paystack_enabled' => 'boolean',
        'paystack_secret_key' => 'encrypted',
        'mpesa_passkey' => 'encrypted',
        'mpesa_consumer_key' => 'encrypted',
        'mpesa_consumer_secret' => 'encrypted',
        'mpesa_b2c_password' => 'encrypted',
        'mpesa_b2c_security_credential' => 'encrypted',
        'intasend_enabled' => 'boolean',
        'intasend_secret_key' => 'encrypted',
        'intasend_webhook_challenge' => 'encrypted',
        'bank_account_number' => 'encrypted',
        'coop_webhook_secret' => 'encrypted',
        'equity_webhook_secret' => 'encrypted',
        'kcb_webhook_secret' => 'encrypted',
    ];

    /**
     * CRYPTO-11: per-landlord bank webhook secret lookup. Returns null
     * when no per-landlord secret is configured — caller should fall
     * back to the env-wide secret. Uses withoutGlobalScopes because the
     * lookup runs in the webhook request context, before any auth.
     */
    public static function webhookSecretFor(int $landlordId, string $bankCode): ?string
    {
        $column = match ($bankCode) {
            'coop', 'equity', 'kcb' => "{$bankCode}_webhook_secret",
            default => null,
        };

        if ($column === null) {
            return null;
        }

        $config = static::withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->first();

        $secret = $config?->{$column};

        return $secret === '' ? null : $secret;
    }

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
     * M-Pesa environment options
     */
    const MPESA_ENVIRONMENTS = [
        'sandbox' => 'Sandbox (Testing)',
        'production' => 'Production (Live)',
    ];

    const INTASEND_ENVIRONMENTS = [
        'sandbox' => 'Sandbox (Testing)',
        'production' => 'Production (Live)',
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
     * Check if M-Pesa API credentials are configured (consumer key + secret)
     */
    public function hasMpesaApiConfig(): bool
    {
        return ! empty($this->mpesa_consumer_key) && ! empty($this->mpesa_consumer_secret);
    }

    /**
     * Check if M-Pesa B2C (refunds) is configured
     */
    public function hasMpesaB2CConfig(): bool
    {
        return ! empty($this->mpesa_b2c_shortcode)
            && ! empty($this->mpesa_b2c_initiator)
            && ! empty($this->mpesa_b2c_password)
            && ! empty($this->mpesa_b2c_security_credential);
    }

    /**
     * Check if Paystack is fully configured
     */
    public function hasPaystackConfig(): bool
    {
        return $this->paystack_enabled
            && ! empty($this->paystack_public_key)
            && ! empty($this->paystack_secret_key);
    }

    /**
     * Phase-40 GATEWAY-STRIPE-3: per-tenant Stripe credentials live on
     * payment_configurations alongside Paystack. Columns may not exist
     * yet pre-Phase-1b migration — guard with array-key check before
     * touching $this->attributes.
     */
    public function hasStripeConfig(): bool
    {
        if (! array_key_exists('stripe_enabled', $this->attributes ?? [])) {
            return false;
        }

        return (bool) $this->stripe_enabled
            && ! empty($this->stripe_public_key)
            && ! empty($this->stripe_secret_key);
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
     * Check if IntaSend is fully configured
     */
    public function hasIntaSendConfig(): bool
    {
        return $this->intasend_enabled
            && ! empty($this->intasend_publishable_key)
            && ! empty($this->intasend_secret_key);
    }

    /**
     * Get IntaSend API base URL based on environment
     */
    public function getIntaSendBaseUrl(): string
    {
        $productionUrl = config('intasend.endpoints.production') ?? 'https://payment.intasend.com';
        $sandboxUrl = config('intasend.endpoints.sandbox') ?? 'https://sandbox.intasend.com';

        return $this->intasend_environment === 'production'
            ? $productionUrl
            : $sandboxUrl;
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
                'intasend_enabled' => false,
                'intasend_environment' => 'sandbox',
            ]
        );
    }
}
