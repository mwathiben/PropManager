<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AlertFiring;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Phase-32 SRE-ALERT-3: fired once per OPEN-state insert by
 * AlertFiringRecorder::record. Update-to-existing-open doesn't
 * dispatch a duplicate event.
 */
class AlertFiringRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly AlertFiring $firing,
    ) {}
}
