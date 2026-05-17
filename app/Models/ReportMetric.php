<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-50 CUSTOM-METRICS-1: per-landlord named formula used as a
 * derived column on a report run.
 *
 * parsed_rpn is the Shunting-Yard output produced by
 * MetricFormulaService::parse on write — DO NOT trust the raw
 * expression at evaluation time; always reuse the cached RPN.
 */
class ReportMetric extends Model
{
    use SoftDeletes, TenantScope;

    protected $fillable = [
        'landlord_id',
        'slug',
        'name',
        'expression',
        'parsed_rpn',
        'unit',
        'is_active',
    ];

    protected $casts = [
        'parsed_rpn' => 'array',
        'is_active' => 'boolean',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
