<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LeaseTermination;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-61 TERMINATION-2: emitted when a termination is initiated.
 * Downstream listeners can notify the other party via email/SMS,
 * log to incident-detector, etc.
 */
class LeaseTerminationInitiated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly LeaseTermination $termination) {}
}
