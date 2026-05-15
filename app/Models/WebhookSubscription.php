<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase-25 API-WEBHOOK-1: a landlord's registered outbound webhook
 * endpoint. Multiple subscriptions per landlord are allowed (e.g. one
 * for QuickBooks Sync, one for Zapier, one for an internal ERP).
 *
 * The `secret` is per-subscription HMAC-SHA256 signing key — the
 * outbound payload includes X-PropManager-Signature: sha256=<hex>
 * so the receiver can authenticate the source.
 *
 * `events` is a JSON array of event-type strings (payment.received,
 * invoice.created, etc.). The DeliverWebhookJob dispatcher consults
 * this filter before queueing — a subscription that has not opted
 * into an event type never receives it.
 *
 * @property int $id
 * @property int $landlord_id
 * @property string $url
 * @property string $secret
 * @property array<int, string> $events
 * @property bool $active
 * @property \Carbon\Carbon|null $last_delivery_at
 * @property-read User $landlord
 * @property-read \Illuminate\Database\Eloquent\Collection<WebhookDelivery> $deliveries
 */
class WebhookSubscription extends Model
{
    use Auditable, HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'url',
        'secret',
        'events',
        'active',
        'last_delivery_at',
    ];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
        'last_delivery_at' => 'datetime',
    ];

    protected $hidden = [
        // The secret is shown ONCE at creation (like a Sanctum PAT)
        // and then hidden from API + Inertia responses. Operator who
        // forgets it must regenerate the subscription.
        'secret',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Whether this subscription has opted into the given event type.
     */
    public function subscribesTo(string $eventType): bool
    {
        return $this->active && in_array($eventType, $this->events ?? [], true);
    }
}
