<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-73 REPORT-SHARE: a time-boxed, revocable share of a saved report.
 * Landlord-scoped (TenantScope). isActive() is the belt-and-suspenders check
 * the public signed view route runs alongside the URL signature.
 */
class ReportShare extends Model
{
    use TenantScope;

    protected $fillable = [
        'landlord_id',
        'saved_report_id',
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

    public function savedReport(): BelongsTo
    {
        return $this->belongsTo(SavedReport::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
