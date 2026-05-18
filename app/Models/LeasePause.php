<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeasePause extends Model
{
    use SoftDeletes;
    use TenantScope;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const REASON_TENANT_HARDSHIP = 'tenant_hardship';

    public const REASON_LANDLORD_RENOVATION = 'landlord_renovation';

    public const REASON_MUTUAL = 'mutual';

    public const REASON_OTHER = 'other';

    protected $fillable = [
        'lease_id',
        'landlord_id',
        'initiated_by',
        'pause_start',
        'pause_end',
        'reason',
        'reason_text',
        'auto_resumed',
        'status',
    ];

    protected $casts = [
        'pause_start' => 'date',
        'pause_end' => 'date',
        'auto_resumed' => 'boolean',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}
