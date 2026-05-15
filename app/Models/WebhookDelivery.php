<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-25 API-WEBHOOK-2: per-attempt outbound webhook delivery log.
 *
 * Every dispatch attempt (including retries) writes one row here so
 * landlords can diagnose flaky integrator endpoints. A failed
 * delivery's `attempt` increments via the DeliverWebhookJob retry
 * pattern; after 5 attempts the row gets `dead_lettered = true` and
 * stops retrying.
 *
 * @property int $id
 * @property int $webhook_subscription_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property int $attempt
 * @property int|null $http_status
 * @property string|null $response_body
 * @property string|null $error
 * @property \Carbon\Carbon|null $dispatched_at
 * @property \Carbon\Carbon|null $completed_at
 * @property bool $dead_lettered
 * @property-read WebhookSubscription $subscription
 */
class WebhookDelivery extends Model
{
    use HasFactory;

    public const MAX_ATTEMPTS = 5;

    protected $fillable = [
        'webhook_subscription_id',
        'event_type',
        'payload',
        'attempt',
        'http_status',
        'response_body',
        'error',
        'dispatched_at',
        'completed_at',
        'dead_lettered',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempt' => 'integer',
        'http_status' => 'integer',
        'dead_lettered' => 'boolean',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }

    public function isSuccessful(): bool
    {
        return $this->http_status !== null
            && $this->http_status >= 200
            && $this->http_status < 300;
    }

    public function canRetry(): bool
    {
        return ! $this->dead_lettered
            && ! $this->isSuccessful()
            && $this->attempt < self::MAX_ATTEMPTS;
    }
}
