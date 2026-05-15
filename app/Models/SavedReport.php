<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-27 BI-BUILDER-1: landlord-configured saved report.
 *
 * config JSON is validated by ReportBuilderService at write time —
 * never trust it on read. The model is intentionally thin; all
 * report execution lives in ReportBuilderService::run().
 */
class SavedReport extends Model
{
    use Auditable, TenantScope;

    protected $fillable = [
        'landlord_id',
        'name',
        'description',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
