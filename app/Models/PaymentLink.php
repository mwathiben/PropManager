<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLink extends Model
{
    use TenantScope;

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

    // ==================== Scopes ====================

    public function scopeValid($query)
    {
        return $query->where('is_revoked', false)
            ->where('expires_at', '>', now())
            ->whereHas('invoice', function ($q) {
                $q->whereNotIn('status', ['paid', 'cancelled', 'voided']);
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeRevoked($query)
    {
        return $query->where('is_revoked', true);
    }

    public function scopeUnclicked($query)
    {
        return $query->whereNull('clicked_at');
    }

    public function scopeClicked($query)
    {
        return $query->whereNotNull('clicked_at');
    }

    // ==================== Helpers ====================

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function isExpired(): bool
    {
        return $this->expires_at < now();
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

        return $invoice && ! in_array($invoice->status, ['paid', 'cancelled', 'voided']);
    }

    public function markClicked(?string $ip = null): void
    {
        if (! $this->clicked_at) {
            $this->update([
                'clicked_at' => now(),
                'clicked_ip' => $ip,
            ]);
        }
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
