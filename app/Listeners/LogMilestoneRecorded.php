<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MilestoneRecorded;
use App\Services\WorkflowLogger;

/**
 * Phase-31 ONB-TTFI-3: route milestone hits into the unified
 * workflow_runs_log so the Phase-29 workflow:health silent-failure
 * dashboard surfaces "no first_invoice this week" alongside Phase-29
 * rent-reminder + lease-renewal cadence.
 */
class LogMilestoneRecorded
{
    public function __construct(
        private readonly WorkflowLogger $logger,
    ) {}

    public function handle(MilestoneRecorded $event): void
    {
        $this->logger->log(
            workflowName: 'onboarding:milestone',
            action: (string) $event->milestone->milestone,
            landlordId: (int) $event->milestone->landlord_id,
            targetType: \App\Models\OnboardingMilestone::class,
            targetId: (int) $event->milestone->id,
            metadata: $event->milestone->metadata ?? [],
        );
    }
}
