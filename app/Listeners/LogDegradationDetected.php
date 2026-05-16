<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DegradationDetected;
use App\Services\WorkflowLogger;

/**
 * Phase-32 SRE-DEPS-3: route dep transitions into workflow_runs_log
 * so workflow:health surfaces upstream degradations alongside cron
 * health.
 */
class LogDegradationDetected
{
    public function __construct(
        private readonly WorkflowLogger $logger,
    ) {}

    public function handle(DegradationDetected $event): void
    {
        $this->logger->log(
            workflowName: 'dependency:degradation',
            action: $event->previousStatus.'_to_'.$event->currentStatus,
            landlordId: null,
            targetType: null,
            targetId: null,
            metadata: [
                'dependency' => $event->dependency,
                'previous_status' => $event->previousStatus,
                'current_status' => $event->currentStatus,
                'latency_ms' => $event->latencyMs,
            ],
        );
    }
}
