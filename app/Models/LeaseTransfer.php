<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaseTransfer extends Model
{
    use SoftDeletes;
    use TenantScope;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_LANDLORD_APPROVED = 'landlord_approved';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'lease_id',
        'landlord_id',
        'outgoing_tenant_id',
        'incoming_tenant_id',
        'initiated_by',
        'transfer_date',
        'landlord_approved_at',
        'status',
        'transfer_fee_amount',
        'reason_text',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'landlord_approved_at' => 'datetime',
        'transfer_fee_amount' => 'decimal:2',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function outgoingTenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outgoing_tenant_id');
    }

    public function incomingTenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'incoming_tenant_id');
    }
}
