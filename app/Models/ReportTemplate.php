<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-50 TEMPLATE-MARKETPLACE-1: platform-curated report templates.
 *
 * Intentionally has NO TenantScope and NO landlord_id — these rows are
 * global and read by every landlord. Cloning produces a per-landlord
 * SavedReport via ReportTemplateService::cloneFor.
 *
 * config JSON shape mirrors SavedReport.config (validated by
 * ReportBuilderService::run on first execution). Seeder enforces the
 * shape; do not edit live rows by hand without re-running the seeder.
 */
class ReportTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'category',
        'description',
        'config',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
