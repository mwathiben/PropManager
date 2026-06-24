<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PaymentPlan;
use App\Models\User;

/**
 * Phase-29 WF-PAY-APPROVE-1: only the plan's landlord can approve or
 * reject. Tenants own creation (Phase-28 TENANT-PAY-1) but cannot
 * advance status — that's the landlord's prerogative.
 */
class PaymentPlanPolicy
{
    public function manage(User $user, PaymentPlan $plan): bool
    {
        return $user->isScopeOwner() && $plan->landlord_id === $user->id;
    }
}
