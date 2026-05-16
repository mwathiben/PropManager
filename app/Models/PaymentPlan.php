<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase-28 TENANT-PAY-1: a tenant-initiated installment plan against
 * a specific invoice. Status machine guarded by app-layer checks in
 * the controllers (DB-level enum prevents nonsense values but does
 * not enforce transitions). TenantScope auto-filters by landlord_id.
 */
class PaymentPlan extends Model
{
    use TenantScope;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DEFAULTED = 'defaulted';

    public const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_COMPLETED,
        self::STATUS_DEFAULTED,
    ];

    protected $fillable = [
        'landlord_id',
        'tenant_id',
        'invoice_id',
        'total_amount_cents',
        'status',
        'reason',
        'rejection_reason',
        'approved_at',
        'approved_by_user_id',
    ];

    protected $casts = [
        'total_amount_cents' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentPlanInstallment::class)->orderBy('due_date');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_APPROVED], true);
    }
}
