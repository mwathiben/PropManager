<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-57 SLOW-QUERY-1: structured SQL-table sink for the slow query
 * stream.
 *
 * Phase 21 SlowQueryReport scans storage/logs/slow-query-*.log files;
 * file-based makes per-landlord attribution + dashboard rendering hard.
 * This parallel SQL table lets dashboards query it like any other model
 * and aggregate by sql_shape (normalised SQL with literal placeholders
 * stripped — see SqlShapeNormaliser).
 *
 * Opt-in via SLOW_QUERY_PERSIST_TO_TABLE env (config
 * 'observability.slow_query.persist_to_table', default false) so dev/CI
 * can keep the file-only behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slow_query_log_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('landlord_id')->nullable();
            $table->string('sql_shape', 500);
            $table->unsignedInteger('duration_ms');
            $table->string('connection_name', 32)->default('mysql');
            $table->timestamp('executed_at');
            $table->softDeletes();

            $table->index('executed_at', 'sql_exec_idx');
            $table->index(['landlord_id', 'executed_at'], 'sql_landlord_exec_idx');
        });

        Schema::create('slow_query_log_weekly_rollups', function (Blueprint $table): void {
            $table->id();
            $table->date('week_start_date');
            $table->string('sql_shape', 500);
            $table->unsignedBigInteger('landlord_id')->nullable();
            $table->unsignedInteger('occurrence_count');
            $table->unsignedInteger('p95_duration_ms');
            $table->unsignedInteger('max_duration_ms');
            $table->text('sample_sql_truncated');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['week_start_date', 'sql_shape', 'landlord_id'],
                'sqr_unique_week_shape_landlord',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slow_query_log_weekly_rollups');
        Schema::dropIfExists('slow_query_log_entries');
    }
};
