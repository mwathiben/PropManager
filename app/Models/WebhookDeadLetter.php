<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDeadLetter extends Model
{
    use Auditable, HasFactory, TenantScope;

    protected $table = 'webhook_dead_letters';

    public const PROVIDER_MPESA = 'mpesa';

    public const PROVIDER_PAYSTACK = 'paystack';

    public const PROVIDER_INTASEND = 'intasend';

    public const PROVIDER_BANK = 'bank';

    public const ERROR_TRANSIENT = 'transient';

    public const ERROR_PERMANENT = 'permanent';

    public const ERROR_SCHEMA = 'schema';

    public const ERROR_AUTH = 'auth';

    protected $fillable = [
        'landlord_id',
        'provider',
        'event_type',
        'payload',
        'headers',
        'error_reason',
        'error_class',
        'attempts',
        'max_retries',
        'next_retry_at',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'resolved_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'attempts' => 'integer',
        'max_retries' => 'integer',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }

    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    public function scopeRecentFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('error_class', self::ERROR_TRANSIENT)
            ->whereColumn('attempts', '<', 'max_retries')
            ->where('next_retry_at', '<=', now())
            ->whereNull('resolved_at');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function isUnresolved(): bool
    {
        return $this->resolved_at === null;
    }

    public function isRetryable(): bool
    {
        return $this->error_class === self::ERROR_TRANSIENT
            && $this->attempts < $this->max_retries
            && ($this->next_retry_at === null || $this->next_retry_at->isPast())
            && $this->resolved_at === null;
    }

    public function markResolved(User $user, string $notes): self
    {
        $this->update([
            'resolved_at' => now(),
            'resolved_by' => $user->id,
            'resolution_notes' => $notes,
        ]);

        return $this;
    }

    public function incrementAttempts(): self
    {
        $this->update([
            'attempts' => $this->attempts + 1,
            'next_retry_at' => now()->addMinutes(5 * ($this->attempts + 1)),
        ]);

        return $this;
    }
}
