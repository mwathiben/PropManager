<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LeaseRenewalApproaching;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Phase-29 WF-LEASE-RENEW-1: notifies both tenant and landlord when a
 * lease approaches its end_date in one of the configured buckets.
 * Phase-16 RESIL backoff for transient delivery failures.
 */
class NotifyOnLeaseRenewalApproaching implements ShouldQueue
{
    /** @var int */
    public $tries = 4;

    /** @var int[] */
    public $backoff = [30, 60, 300, 1800];

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle(LeaseRenewalApproaching $event): void
    {
        $lease = $event->lease;
        $landlordId = $lease->landlord_id;

        $subject = __('workflow.lease_renewal.subject', [
            'days' => $event->bucketDays,
        ]);
        $message = __('workflow.lease_renewal.body', [
            'days' => $event->bucketDays,
            'end_date' => $lease->end_date?->toDateString(),
        ]);
        $data = [
            'lease_id' => $lease->id,
            'bucket_days' => $event->bucketDays,
        ];

        // Tenant first.
        if ($lease->tenant_id) {
            $this->notifications->send(
                recipientId: $lease->tenant_id,
                type: 'lease_renewal',
                subject: $subject,
                message: $message,
                data: $data,
                landlordId: $landlordId,
            );
        }

        // Landlord next — needs to action the renewal.
        $this->notifications->send(
            recipientId: $landlordId,
            type: 'lease_renewal',
            subject: $subject,
            message: $message,
            data: $data,
            landlordId: $landlordId,
        );
    }
}
