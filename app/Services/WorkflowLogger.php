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

    /**
     * Phase-36 INSIGHT-CRON-1: wrap a Closure with timing + log.
     *
     * Captures wall time around the closure body, writes one row
     * with duration_ms + started_at populated. Re-throws any
     * exception from the closure after attempting the log write —
     * instrumentation must not swallow domain errors. action is
     * suffixed with ':error' when the closure throws so the
     * cron-budget audit can distinguish successful from failed
     * runs.
     */
    public function measure(
        string $workflowName,
        string $action,
        \Closure $body,
        ?int $landlordId = null,
        ?array $metadata = null,
    ): mixed {
        $startedAtWall = now();
        $startedAt = microtime(true);
        $exception = null;
        $result = null;

        try {
            $result = $body();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        try {
            WorkflowRunLog::create([
                'landlord_id' => $landlordId,
                'workflow_name' => $workflowName,
                'action' => $exception === null ? $action : $action.':error',
                'duration_ms' => $durationMs,
                'started_at' => $startedAtWall,
                'metadata' => $metadata,
                'fired_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('WorkflowLogger measure write failed', [
                'workflow_name' => $workflowName,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }
}
