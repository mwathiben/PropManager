<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledReport extends Model
{
    use Auditable, TenantScope;

    public const CADENCES = ['weekly', 'monthly', 'quarterly'];

    protected $fillable = [
        'landlord_id',
        'saved_report_id',
        'cadence',
        'recipient_email',
        'next_due_at',
        'last_sent_at',
    ];

    protected $casts = [
        'next_due_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    public function savedReport(): BelongsTo
    {
        return $this->belongsTo(SavedReport::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Advance next_due_at by the cadence interval. Stamps last_sent_at
     * with now() too.
     */
    public function markSent(): void
    {
        $base = Carbon::now();
        $this->forceFill([
            'last_sent_at' => $base,
            'next_due_at' => match ($this->cadence) {
                'weekly' => $base->copy()->addWeek(),
                'monthly' => $base->copy()->addMonth(),
                'quarterly' => $base->copy()->addMonths(3),
                default => $base->copy()->addWeek(),
            },
        ])->save();
    }
}
