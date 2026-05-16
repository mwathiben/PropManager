<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PaymentPlan;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentPlanRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly PaymentPlan $plan)
    {
    }
}
