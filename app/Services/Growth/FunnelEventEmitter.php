<?php

declare(strict_types=1);

namespace App\Services\Growth;

use App\Models\User;
use App\Services\Platform\ProductEventTracker;

/**
 * Phase-56 FUNNEL-SANKEY-1: emit a canonical funnel stage event into the
 * product_events stream. Wraps ProductEventTracker with the deterministic
 * 'funnel.<stage>' naming convention the Sankey rollup queries on.
 */
class FunnelEventEmitter
{
    public function __construct(private readonly ProductEventTracker $tracker) {}

    public function emit(User $user, FunnelStage $stage, array $extra = []): void
    {
        $this->tracker->track($stage->eventName(), $extra, $user);
    }
}
