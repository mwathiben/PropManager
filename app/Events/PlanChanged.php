<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-60 PLAN-CHANGE-1: emitted after a successful plan switch.
 * Downstream listeners can email the landlord, update Intercom,
 * recompute MRR, etc.
 */
class PlanChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly SubscriptionPlan $fromPlan,
        public readonly SubscriptionPlan $toPlan,
        public readonly User $initiatedBy,
    ) {}
}
