<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LeaseTransfer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-61 TRANSFER-2: emitted when an outgoing tenant requests a
 * lease transfer. Listeners notify the landlord (approval needed)
 * + the incoming tenant (you've been nominated).
 */
class LeaseTransferRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly LeaseTransfer $transfer) {}
}
