<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DriftResolveMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-42 PLAN-SYNC-AUTO-2: append-only log of every Stripe Price
 * drift detection so operators can audit what drifted, when, by
 * how much, and what was resolved.
 */
class SubscriptionPlanDriftLog extends Model
{
    public const RESOLUTION_PENDING = 'pending';

    public const RESOLUTION_APP_WINS = 'resolved_app_wins';

    public const RESOLUTION_STRIPE_WINS = 'resolved_stripe_wins';

    public const RESOLUTION_MANUAL_PENDING = 'manual_pending';

    protected $table = 'subscription_plan_drift_log';

    public $timestamps = false;

    protected $fillable = [
        'subscription_plan_id',
        'stripe_price_id',
        'app_price_cents',
        'stripe_price_cents',
        'drift_resolve_mode_at_time',
        'resolution',
        'detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'app_price_cents' => 'integer',
        'stripe_price_cents' => 'integer',
        'drift_resolve_mode_at_time' => DriftResolveMode::class,
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isPending(): bool
    {
        return in_array($this->resolution, [self::RESOLUTION_PENDING, self::RESOLUTION_MANUAL_PENDING], true);
    }
}
