<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    use HasFactory, TenantScope;

    public const PROVIDER_MPESA = 'mpesa';

    public const PROVIDER_INTASEND = 'intasend';

    public const PROVIDER_PAYSTACK = 'paystack';

    public const PROVIDER_BANK = 'bank';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'landlord_id',
        'provider',
        'event_id',
        'event_type',
        'payload_hash',
        'retry_count',
        'first_received_at',
        'last_received_at',
        'status',
        'processing_time_ms',
        'ip_address',
    ];

    protected $casts = [
        'retry_count' => 'integer',
        'processing_time_ms' => 'integer',
        'first_received_at' => 'datetime',
        'last_received_at' => 'datetime',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    public function scopeHighRetry(Builder $query, int $threshold = 3): Builder
    {
        return $query->where('retry_count', '>=', $threshold);
    }

    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('last_received_at', '>=', now()->subHours($hours));
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function markProcessed(int $processingTimeMs): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSED,
            'processing_time_ms' => $processingTimeMs,
        ]);
    }

    public function markFailed(int $processingTimeMs): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'processing_time_ms' => $processingTimeMs,
        ]);
    }

    public function isRetry(): bool
    {
        return $this->retry_count > 1;
    }
}
