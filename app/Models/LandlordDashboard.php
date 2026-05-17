<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-50 LANDLORD-DASHBOARDS-1: composable landlord dashboard.
 *
 * layout JSON is validated by DashboardService::buildPayload on every
 * render — the migration stores opaque JSON so layout-shape evolutions
 * don't drag schema churn. Never trust the layout array on read.
 */
class LandlordDashboard extends Model
{
    use SoftDeletes, TenantScope;

    protected $fillable = [
        'landlord_id',
        'slug',
        'name',
        'description',
        'layout',
        'is_default',
    ];

    protected $casts = [
        'layout' => 'array',
        'is_default' => 'boolean',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
