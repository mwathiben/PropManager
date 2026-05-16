<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DepositRefundRequest;
use App\Models\User;

/**
 * Phase-29 WF-PAY-APPROVE-2: only the request's landlord can approve,
 * reject, or mark paid. Mirrors PaymentPlanPolicy::manage.
 */
class DepositRefundRequestPolicy
{
    public function manage(User $user, DepositRefundRequest $refund): bool
    {
        return $user->isLandlord() && $refund->landlord_id === $user->id;
    }
}
