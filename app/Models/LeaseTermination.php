<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaseTermination extends Model
{
    use SoftDeletes;
    use TenantScope;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_DISPUTED = 'disputed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const REASON_BREACH_TENANT = 'breach_by_tenant';

    public const REASON_BREACH_LANDLORD = 'breach_by_landlord';

    public const REASON_MUTUAL = 'mutual';

    public const REASON_HARDSHIP = 'hardship';

    public const REASON_SALE = 'sale';

    public const REASON_OTHER = 'other';

    protected $fillable = [
        'lease_id',
        'landlord_id',
        'initiated_by',
        'termination_reason',
        'termination_date',
        'notice_given_at',
        'acknowledged_at',
        'status',
        'reason_text',
    ];

    protected $casts = [
        'termination_date' => 'date',
        'notice_given_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
