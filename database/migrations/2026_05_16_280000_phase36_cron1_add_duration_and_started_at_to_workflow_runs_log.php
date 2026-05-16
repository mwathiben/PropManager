<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-36 INSIGHT-CRON-1: additive timing columns on
 * workflow_runs_log. WorkflowLogger::measure() wraps Closures
 * with microtime capture and writes both columns; existing
 * WorkflowLogger::log() call sites continue to write NULLs
 * unchanged (backwards-compat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_runs_log', function (Blueprint $table): void {
            $table->unsignedInteger('duration_ms')->nullable()->after('action');
            $table->timestamp('started_at')->nullable()->after('duration_ms');
            $table->index(['workflow_name', 'started_at'], 'wrl_workflow_started_idx');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_runs_log', function (Blueprint $table): void {
            $table->dropIndex('wrl_workflow_started_idx');
            $table->dropColumn(['duration_ms', 'started_at']);
        });
    }
};
