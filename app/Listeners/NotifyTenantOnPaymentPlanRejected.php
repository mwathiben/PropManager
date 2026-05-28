<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentPlanRejected;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyTenantOnPaymentPlanRejected implements ShouldQueue
{
    /** @var int */
    public $tries = 4;

    /** @var int[] */
    public $backoff = [30, 60, 300, 1800];

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(PaymentPlanRejected $event): void
    {
        $plan = $event->plan;
        $this->notifications->send(
            recipientId: $plan->tenant_id,
            type: 'general',
            subject: __('workflow.payment_plan.rejected_subject'),
            message: __('workflow.payment_plan.rejected_body', [
                'reason' => $plan->rejection_reason ?? '',
            ]),
            data: ['payment_plan_id' => $plan->id],
            landlordId: $plan->landlord_id,
        );
    }
}
