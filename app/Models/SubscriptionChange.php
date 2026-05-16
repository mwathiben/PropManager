<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionChange extends Model
{
    public const TYPE_UPGRADE = 'upgrade';
    public const TYPE_DOWNGRADE = 'downgrade';
    public const TYPE_SAME = 'same';

    protected $fillable = [
        'subscription_id',
        'from_plan_id',
        'to_plan_id',
        'change_type',
        'prorated_amount_kes',
        'scheduled_for',
        'effective_at',
    ];

    protected $casts = [
        'prorated_amount_kes' => 'decimal:2',
        'scheduled_for' => 'datetime',
        'effective_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'from_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'to_plan_id');
    }
}
