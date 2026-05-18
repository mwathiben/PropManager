<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-57 SLOW-QUERY-1: a single slow-query observation captured by the
 * SlowQueryServiceProvider when SLOW_QUERY_PERSIST_TO_TABLE is set.
 *
 * No TenantScope because landlord_id is nullable (a request without an
 * authenticated user has no landlord context). Dashboards filter via
 * explicit ->where('landlord_id', ...) when scoped.
 */
class SlowQueryLogEntry extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'landlord_id',
        'sql_shape',
        'duration_ms',
        'connection_name',
        'executed_at',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
    ];
}
