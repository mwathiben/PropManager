<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandlordPayoutAccount extends Model
{
    use Auditable, HasFactory, TenantScope;

    /**
     * AUDIT-4: never write the full account number to the AuditLog —
     * even the model's $hidden array doesn't reach the audit pipeline.
     */
    public function getAuditExclude(): array
    {
        return ['account_number'];
    }

    protected $fillable = [
        'landlord_id',
        'provider',
        'subaccount_code',
        'account_type',
        'account_number',
        'account_name',
        'bank_code',
        'bank_name',
        'mobile_number',
        'business_name',
        'settlement_bank',
        'percentage_charge',
        'flat_charge',
        'verification_status',
        'rejection_reason',
        'is_active',
        'is_primary',
        'verified_at',
        'metadata',
    ];

    protected $casts = [
        'percentage_charge' => 'decimal:2',
        'flat_charge' => 'decimal:2',
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
        'metadata' => 'array',
        // CRYPTO-4: bank PII at rest. Without these casts the columns sit
        // plaintext in the DB even though $hidden masks them on serialize.
        // PaymentConfiguration.bank_account_number already uses 'encrypted';
        // mirror that convention here. Backfill migration converts existing
        // rows.
        'account_number' => 'encrypted',
        'account_name' => 'encrypted',
        'mobile_number' => 'encrypted',
    ];

    protected $hidden = [
        'account_number',
    ];

    /**
     * Payment providers
     */
    const PROVIDERS = [
        'paystack' => 'Paystack',
        'flutterwave' => 'Flutterwave',
    ];

    /**
     * Account types
     */
    const ACCOUNT_TYPES = [
        'bank' => 'Bank Account',
        'mobile_money' => 'Mobile Money (M-Pesa)',
    ];

    /**
     * Verification statuses
     */
    const VERIFICATION_STATUSES = [
        'pending' => 'Pending Verification',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        'suspended' => 'Suspended',
    ];

    /**
     * Get the landlord
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get payments made to this account
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payout_account_id');
    }

    /**
     * Get platform fees for this account
     */
    public function platformFees(): HasMany
    {
        return $this->hasMany(PlatformFee::class, 'payout_account_id');
    }

    /**
     * Check if account is verified
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /**
     * Check if account is pending
     */
    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    /**
     * Check if account is rejected
     */
    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

    /**
     * Check if account can receive payments
     */
    public function canReceivePayments(): bool
    {
        return $this->is_active && $this->isVerified() && ! empty($this->subaccount_code);
    }

    /**
     * Get masked account number
     */
    protected function maskedAccountNumber(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (empty($this->attributes['account_number'])) {
                    return '';
                }
                $number = $this->attributes['account_number'];
                $length = strlen($number);
                if ($length <= 4) {
                    return $number;
                }

                return str_repeat('*', $length - 4).substr($number, -4);
            }
        );
    }

    /**
     * Get verification status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::VERIFICATION_STATUSES[$this->verification_status] ?? $this->verification_status;
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->verification_status) {
            'verified' => 'green',
            'pending' => 'yellow',
            'rejected' => 'red',
            'suspended' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get provider label
     */
    public function getProviderLabelAttribute(): string
    {
        return self::PROVIDERS[$this->provider] ?? $this->provider;
    }

    /**
     * Get account type label
     */
    public function getAccountTypeLabelAttribute(): string
    {
        return self::ACCOUNT_TYPES[$this->account_type] ?? $this->account_type;
    }

    /**
     * Scope for primary accounts
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope for active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for verified accounts
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    /**
     * Scope by provider
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Mark account as verified
     */
    public function markAsVerified(): self
    {
        $this->update([
            'verification_status' => 'verified',
            'is_active' => true,
            'verified_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark account as rejected
     */
    public function markAsRejected(?string $reason = null): self
    {
        $this->update([
            'verification_status' => 'rejected',
            'is_active' => false,
            'rejection_reason' => $reason,
        ]);

        return $this;
    }

    /**
     * Set as primary account (removes primary from others)
     */
    public function setAsPrimary(): self
    {
        // Remove primary from other accounts
        self::where('landlord_id', $this->landlord_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);

        return $this;
    }
}
