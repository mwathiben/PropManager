<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MrrSnapshot extends Model
{
    protected $fillable = [
        'day',
        'plan_id',
        'mrr_kes',
        'active_subscriptions',
        'new_mrr_kes',
        'expansion_mrr_kes',
        'contraction_mrr_kes',
        'churned_mrr_kes',
    ];

    protected $casts = [
        'day' => 'date',
        'mrr_kes' => 'decimal:2',
        'active_subscriptions' => 'integer',
        'new_mrr_kes' => 'decimal:2',
        'expansion_mrr_kes' => 'decimal:2',
        'contraction_mrr_kes' => 'decimal:2',
        'churned_mrr_kes' => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
