<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase-29 WF-CI-2: workflow_runs_log row. Cross-tenant readable —
 * the workflow:health command and per-workflow audit queries iterate
 * across all landlords.
 */
class WorkflowRunLog extends Model
{
    protected $table = 'workflow_runs_log';

    protected $fillable = [
        'landlord_id',
        'workflow_name',
        'target_type',
        'target_id',
        'action',
        'metadata',
        'fired_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'fired_at' => 'datetime',
    ];

    public function scopeForWorkflow(Builder $query, string $workflowName): Builder
    {
        return $query->where('workflow_name', $workflowName);
    }

    public function scopeInLast24h(Builder $query): Builder
    {
        return $query->where('fired_at', '>=', now()->subDay());
    }
}
