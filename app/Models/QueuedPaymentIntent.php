<?php

namespace App\Models;

use App\Enums\Currency;
use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueuedPaymentIntent extends Model
{
    use Auditable, HasFactory, TenantScope;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    private const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_EXPIRED,
    ];

    protected $fillable = [
        'idempotency_key',
        'tenant_id',
        'invoice_id',
        'landlord_id',
        'amount',
        'currency',
        'payment_method',
        'phone_number',
        'status',
        'attempts',
        'last_attempt_at',
        'next_retry_at',
        'expires_at',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'currency' => Currency::class,
        'attempts' => 'integer',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeByTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where(function (Builder $q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES);
    }

    public function markProcessing(): self
    {
        $attempts = $this->attempts + 1;
        $backoff = config('payments.queued_intents.backoff', [10, 30, 60, 120, 300]);
        $backoffIndex = min($attempts - 1, count($backoff) - 1);

        $this->update([
            'status' => self::STATUS_PROCESSING,
            'attempts' => $attempts,
            'last_attempt_at' => now(),
            'next_retry_at' => now()->addSeconds($backoff[$backoffIndex]),
        ]);

        return $this;
    }

    public function markCompleted(): self|false
    {
        if ($this->isTerminal()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'next_retry_at' => null,
        ]);

        return $this;
    }

    public function markFailed(string $reason): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
        ]);

        return $this;
    }

    public function markExpired(): self
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);

        return $this;
    }

    public static function generateIdempotencyKey(int $tenantId, ?int $invoiceId, string $nonce): string
    {
        return hash('sha256', "{$tenantId}:{$invoiceId}:{$nonce}");
    }
}
