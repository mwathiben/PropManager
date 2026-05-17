<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase-42 CART-1: tenant-facing cart that bundles multiple
 * invoice/add-on/deposit lines into a single submitted checkout.
 * Multi-currency aware — CartCheckoutService groups items by
 * currency into N PaymentIntents (one per currency).
 */
class CheckoutSession extends Model
{
    use HasFactory, TenantScope;

    public const STATUS_OPEN = 'open';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_SUBMITTED,
        self::STATUS_SUCCEEDED,
        self::STATUS_FAILED,
        self::STATUS_EXPIRED,
    ];

    public const OPEN_STATUSES = [self::STATUS_OPEN, self::STATUS_SUBMITTED];

    protected $fillable = [
        'landlord_id',
        'tenant_id',
        'status',
        'total_amount_cents',
        'currency_breakdown',
        'expires_at',
        'succeeded_at',
    ];

    protected $casts = [
        'total_amount_cents' => 'integer',
        'currency_breakdown' => 'array',
        'expires_at' => 'datetime',
        'succeeded_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CheckoutSessionItem::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeExpiringSoon(Builder $q, int $minutes = 30): Builder
    {
        return $q->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addMinutes($minutes))
            ->where('expires_at', '>', now());
    }
}
