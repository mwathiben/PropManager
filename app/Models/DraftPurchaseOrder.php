<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-54 PARTS-REORDER-1/2: draft purchase order materialised by
 * the parts:reorder-suggest cron from parts.qty_available <=
 * parts.reorder_threshold rows.
 */
class DraftPurchaseOrder extends Model
{
    use SoftDeletes, TenantScope;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_SENT, self::STATUS_CANCELLED];

    protected $fillable = [
        'landlord_id',
        'suggested_vendor_id',
        'status',
        'notes',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'suggested_vendor_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DraftPurchaseOrderLine::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function totalEstimateCents(): int
    {
        return (int) $this->lines()->sum(\DB::raw('qty_suggested * cost_per_unit_cents_snapshot'));
    }
}
