<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MpesaB2cRequest extends Model
{
    use TenantScope;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_TIMED_OUT = 'timed_out';

    public const OPEN_STATUSES = [self::STATUS_QUEUED, self::STATUS_SENT];

    protected $fillable = [
        'landlord_id',
        'source_type',
        'source_id',
        'phone',
        'amount_cents',
        'reference',
        'remarks',
        'status',
        'originator_conversation_id',
        'conversation_id',
        'transaction_id',
        'last_response',
        'failure_reason',
        'sent_at',
        'confirmed_at',
        'last_polled_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'last_response' => 'array',
        'sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'last_polled_at' => 'datetime',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
