<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DepositRefundRejected;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyTenantOnDepositRefundRejected implements ShouldQueue
{
    /** @var int */
    public $tries = 4;

    /** @var int[] */
    public $backoff = [30, 60, 300, 1800];

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(DepositRefundRejected $event): void
    {
        $refund = $event->refund;
        $this->notifications->send(
            recipientId: $refund->tenant_id,
            type: 'general',
            subject: __('workflow.deposit_refund.rejected_subject'),
            message: __('workflow.deposit_refund.rejected_body', [
                'reason' => $refund->rejection_reason ?? '',
            ]),
            data: ['deposit_refund_id' => $refund->id],
            landlordId: $refund->landlord_id,
        );
    }
}
