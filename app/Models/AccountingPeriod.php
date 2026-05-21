<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    use HasFactory, TenantScope;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'landlord_id',
        'period_start',
        'period_end',
        'status',
        'closed_at',
        'closed_by_user_id',
        'close_notes',
        'reopened_at',
        'reopened_by_user_id',
        'reopen_reason',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    /**
     * True iff the landlord has a CLOSED period whose [start, end]
     * inclusive window contains $date.
     */
    public static function isDateLocked(int $landlordId, \DateTimeInterface|string $date): bool
    {
        $effective = $date instanceof \DateTimeInterface
            ? CarbonImmutable::instance($date)->toDateString()
            : CarbonImmutable::parse($date)->toDateString();

        return static::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('status', self::STATUS_CLOSED)
            ->where('period_start', '<=', $effective)
            ->where('period_end', '>=', $effective)
            ->exists();
    }
}
