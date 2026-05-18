<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-57 SLOW-QUERY-3: aggregated slow-query observations per
 * (week_start_date, sql_shape, landlord_id). The unique index keeps the
 * rollup idempotent — repeat runs of slow-query:rollup updateOrCreate
 * the same row.
 */
class SlowQueryLogWeeklyRollup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'week_start_date',
        'sql_shape',
        'landlord_id',
        'occurrence_count',
        'p95_duration_ms',
        'max_duration_ms',
        'sample_sql_truncated',
    ];

    protected $casts = [
        'week_start_date' => 'date',
    ];
}
