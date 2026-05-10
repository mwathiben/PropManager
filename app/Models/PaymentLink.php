<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $token
 * @property int $invoice_id
 * @property int $landlord_id
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $clicked_at
 * @property string|null $clicked_ip
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property bool $is_revoked
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $url
 * @property-read int $days_until_expiry
 * @property-read Invoice $invoice
 * @property-read User $landlord
 */
class PaymentLink extends Model
{
    use Auditable, TenantScope;

    protected $fillable = [
        'token',
        'invoice_id',
        'landlord_id',
        'expires_at',
        'clicked_at',
        'clicked_ip',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'is_revoked',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'clicked_at' => 'datetime',
        'is_revoked' => 'boolean',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        return static::withoutGlobalScope('landlord')
            ->where($field ?? 'token', $value)
            ->first();
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    // ==================== Relationships ====================

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * @param  Builder<PaymentLink>  $query
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_revoked', false)
            ->where('expires_at', '>', now())
            ->whereHas('invoice', function ($q) {
                $q->whereNotIn('status', [InvoiceStatus::Paid, InvoiceStatus::Cancelled, InvoiceStatus::Voided]);
            });
    }

    /**
     * @param  Builder<PaymentLink>  $query
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * @param  Builder<PaymentLink>  $query
     */
    public function scopeRevoked(Builder $query): Builder
    {
        return $query->where('is_revoked', true);
    }

    /**
     * @param  Builder<PaymentLink>  $query
     */
    public function scopeUnclicked(Builder $query): Builder
    {
        return $query->whereNull('clicked_at');
    }

    /**
     * @param  Builder<PaymentLink>  $query
     */
    public function scopeClicked(Builder $query): Builder
    {
        return $query->whereNotNull('clicked_at');
    }

    // ==================== Helpers ====================

    public static function generateToken(): string
    {
        return \App\Support\Tokens::secure(32);
    }

    public function isExpired(): bool
    {
        return $this->expires_at <= now();
    }

    public function isRevoked(): bool
    {
        return $this->is_revoked;
    }

    public function isClicked(): bool
    {
        return ! is_null($this->clicked_at);
    }

    public function isValid(): bool
    {
        if ($this->is_revoked || $this->isExpired()) {
            return false;
        }

        $invoice = $this->invoice;

        return $invoice && ! in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled, InvoiceStatus::Voided]);
    }

    public function markClicked(?string $ip = null): void
    {
        static::where('id', $this->id)
            ->whereNull('clicked_at')
            ->update([
                'clicked_at' => now(),
                'clicked_ip' => $ip,
            ]);

        $this->refresh();
    }

    public function revoke(): void
    {
        $this->update(['is_revoked' => true]);
    }

    public function getUrlAttribute(): string
    {
        return route('payment.link', $this->token);
    }

    public function getDaysUntilExpiryAttribute(): int
    {
        return max(0, now()->diffInDays($this->expires_at, false));
    }
}
