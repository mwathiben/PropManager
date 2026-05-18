<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\SlaDefinition;
use App\Services\Maintenance\SlaDefinitionService;

/**
 * Phase-54 SLA-LANDLORD-UI-3: flush SlaDefinitionService cache on any
 * write so a landlord saves an override and sees it on the next ticket
 * created — no 5-minute lag.
 *
 * The service uses a version-stamped cache key, so flushCache just
 * bumps the version counter and the next resolveFor() call computes a
 * fresh key. Both saved + deleted fire because a row's
 * (category, subcategory, priority) tuple changing OR going away both
 * affect cascade outcomes.
 */
class SlaDefinitionObserver
{
    public function __construct(private readonly SlaDefinitionService $service) {}

    public function saved(SlaDefinition $sla): void
    {
        $this->service->flushCache($sla->landlord_id);
    }

    public function deleted(SlaDefinition $sla): void
    {
        $this->service->flushCache($sla->landlord_id);
    }
}
