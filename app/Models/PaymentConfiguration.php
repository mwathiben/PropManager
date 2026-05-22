<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\StripeConnectAccountType;
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
        'tiered_tariffs',
        'water_standing_charge',
        'water_minimum_charge',
        'water_sewerage_percent',
        'water_vat_percent',
        'water_source',
        'water_reading_day',
        'water_review_days',
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
        'stripe_enabled',
        'stripe_public_key',
        'stripe_secret_key',
        'stripe_webhook_secret',
        'stripe_connect_account_id',
        'stripe_connect_account_id_hash',
        'stripe_connect_status',
        'stripe_connect_charges_enabled',
        'stripe_connect_payouts_enabled',
        'stripe_connect_account_type',
        'kra_pin',
        'vat_rate_bps_override',
        'stripe_tax_enabled',
    ];

    protected $casts = [
        'default_rent' => 'decimal:2',
        'flat_water_rate' => 'decimal:2',
        'water_unit_rate' => 'decimal:2',
        'tiered_tariffs' => 'array',
        'water_standing_charge' => 'decimal:2',
        'water_minimum_charge' => 'decimal:2',
        'water_sewerage_percent' => 'decimal:2',
        'water_vat_percent' => 'decimal:2',
        'water_reading_day' => 'integer',
        'water_review_days' => 'integer',
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
        'stripe_enabled' => 'boolean',
        'stripe_secret_key' => 'encrypted',
        'stripe_webhook_secret' => 'encrypted',
        'stripe_connect_account_id' => 'encrypted',
        'stripe_connect_charges_enabled' => 'boolean',
        'stripe_connect_payouts_enabled' => 'boolean',
        'stripe_connect_account_type' => StripeConnectAccountType::class,
        'kra_pin' => 'encrypted',
        'vat_rate_bps_override' => 'integer',
        'stripe_tax_enabled' => 'boolean',
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
     * Phase-42 follow-up: canonical reverse lookup keyed on the SHA256
     * hash. Replaces the O(n) decrypted-scan workaround Phase 42
     * Phase 1f shipped at StripeWebhookController::handlePayoutFailed
     * + Phase-41 StripeConnectService::syncAccountStatus call sites.
     */
    public static function findByConnectAccountId(string $accountId): ?self
    {
        if ($accountId === '') {
            return null;
        }

        return static::query()
            ->where('stripe_connect_account_id_hash', hash('sha256', $accountId))
            ->first();
    }

    /**
     * Phase-42 follow-up: keep stripe_connect_account_id_hash in lock
     * step with the encrypted account id. Hooks the saving event so
     * the 'encrypted' cast's setter runs first; reading the attribute
     * back via the cast decrypts the just-set ciphertext to plaintext.
     */
    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if (! $model->isDirty('stripe_connect_account_id')) {
                return;
            }

            $plain = $model->stripe_connect_account_id;
            $model->attributes['stripe_connect_account_id_hash'] = $plain === null || $plain === ''
                ? null
                : hash('sha256', (string) $plain);
        });
    }

    /**
     * Phase-42 TAX-2: KRA PIN format guard — accepts the standard
     * Kenya VAT PIN shape `A` or `P` + 9 digits + 1 uppercase letter
     * (e.g., A001234567Z, P051234567B). KRA-side enforcement at the
     * filing layer is stricter; this is the format gate only.
     */
    public function isVatRegistered(): bool
    {
        if (! array_key_exists('kra_pin', $this->attributes ?? [])) {
            return false;
        }

        $pin = (string) ($this->kra_pin ?? '');

        return $pin !== '' && preg_match('/^[AP]\d{9}[A-Z]$/', $pin) === 1;
    }

    /**
     * Phase-42 TAX-3: Stripe Tax opt-in. Returns false when the
     * column is missing (pre-migration) or unset.
     */
    public function hasStripeTaxEnabled(): bool
    {
        if (! array_key_exists('stripe_tax_enabled', $this->attributes ?? [])) {
            return false;
        }

        return (bool) ($this->stripe_tax_enabled ?? false);
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
