<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AlertFiringRecorded;
use App\Services\WorkflowLogger;

/**
 * Phase-32 SRE-ALERT-3: route alert firings into the unified
 * workflow_runs_log so the Phase-29 workflow:health dashboard
 * surfaces alert volume alongside scheduled-task health.
 */
class LogAlertFiring
{
    public function __construct(
        private readonly WorkflowLogger $logger,
    ) {}

    public function handle(AlertFiringRecorded $event): void
    {
        $this->logger->log(
            workflowName: 'alert:fired',
            action: (string) $event->firing->alert_key,
            landlordId: null,
            targetType: \App\Models\AlertFiring::class,
            targetId: (int) $event->firing->id,
            metadata: [
                'severity' => $event->firing->severity,
                'value' => $event->firing->value,
                'threshold' => $event->firing->threshold,
            ] + ($event->firing->metadata ?? []),
        );
    }
}
