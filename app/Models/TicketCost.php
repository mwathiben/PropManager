<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-49 MAINTENANCE-COSTS-1: per-ticket cost row. Category captures
 * the source (parts auto-seeded by TicketResolutionService; vendor +
 * labor + other captured by landlord-facing UI in a follow-up cycle).
 *
 * @property int $id
 * @property int $ticket_id
 * @property string $category parts|vendor|labor|other
 * @property int $amount_cents
 * @property string $currency
 * @property int|null $recorded_by
 * @property string|null $notes
 * @property \Carbon\Carbon $recorded_at
 */
class TicketCost extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORY_PARTS = 'parts';

    public const CATEGORY_VENDOR = 'vendor';

    public const CATEGORY_LABOR = 'labor';

    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_PARTS,
        self::CATEGORY_VENDOR,
        self::CATEGORY_LABOR,
        self::CATEGORY_OTHER,
    ];

    protected $fillable = [
        'ticket_id',
        'category',
        'amount_cents',
        'currency',
        'recorded_by',
        'notes',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
