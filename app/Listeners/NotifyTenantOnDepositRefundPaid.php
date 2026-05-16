<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DepositRefundPaid;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyTenantOnDepositRefundPaid implements ShouldQueue
{
    /** @var int */
    public $tries = 4;

    /** @var int[] */
    public $backoff = [30, 60, 300, 1800];

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle(DepositRefundPaid $event): void
    {
        $refund = $event->refund;
        $this->notifications->send(
            recipientId: $refund->tenant_id,
            type: 'general',
            subject: __('workflow.deposit_refund.paid_subject'),
            message: __('workflow.deposit_refund.paid_body', [
                'reference' => $refund->payment_reference ?? '',
            ]),
            data: [
                'deposit_refund_id' => $refund->id,
                // Phase-39 PUSH-EXTEND-1: tap → tenant refund page.
                'url' => '/tenant/refunds',
            ],
            landlordId: $refund->landlord_id,
        );
    }
}
