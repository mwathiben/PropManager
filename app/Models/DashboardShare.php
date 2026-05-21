<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-74 DASH-SHARE: a time-boxed, revocable share of a landlord dashboard.
 * Landlord-scoped (TenantScope). isActive() is the belt-and-suspenders check
 * the public signed view route runs alongside the URL signature. Mirrors
 * ReportShare.
 */
class DashboardShare extends Model
{
    use TenantScope;

    protected $fillable = [
        'landlord_id',
        'landlord_dashboard_id',
        'expires_at',
        'revoked_at',
        'last_viewed_at',
        'view_count',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'view_count' => 'integer',
    ];

    public function landlordDashboard(): BelongsTo
    {
        return $this->belongsTo(LandlordDashboard::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
