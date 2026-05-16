<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\WorkflowRunLog;

/**
 * Phase-29 WF-CI-2: minimal append-only logger for workflow firings.
 * Best-effort — if the write fails we log via Laravel's default
 * channel and swallow the exception so a workflow firing is never
 * cancelled by a failed audit write.
 */
class WorkflowLogger
{
    public function log(
        string $workflowName,
        string $action,
        ?int $landlordId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $metadata = null,
    ): void {
        try {
            WorkflowRunLog::create([
                'landlord_id' => $landlordId,
                'workflow_name' => $workflowName,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'action' => $action,
                'metadata' => $metadata,
                'fired_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('WorkflowLogger write failed', [
                'workflow_name' => $workflowName,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
