<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Phase-32 SRE-DEPS-3: fired by outbound:health-check on transition
 * from up -> degraded|down (or back).
 */
class DegradationDetected
{
    use Dispatchable;

    public function __construct(
        public readonly string $dependency,
        public readonly string $previousStatus,
        public readonly string $currentStatus,
        public readonly int $latencyMs,
    ) {}
}
