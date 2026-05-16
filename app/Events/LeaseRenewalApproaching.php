<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Lease;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-29 WF-LEASE-RENEW-1: fired by leases:scan-renewals when a
 * lease end_date sits exactly $bucketDays days away (one of 60/30/7).
 */
class LeaseRenewalApproaching
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Lease $lease,
        public readonly int $bucketDays,
        public readonly CarbonImmutable $detectedAt,
    ) {
    }
}
