<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class SampleDataRun extends Model
{
    use TenantScope;

    public const STATUS_POPULATED = 'populated';

    public const STATUS_RESET_PENDING = 'reset_pending';

    public const STATUS_RESET_DONE = 'reset_done';

    protected $fillable = [
        'landlord_id',
        'status',
        'populated_at',
        'reset_at',
        'row_refs',
    ];

    protected $casts = [
        'populated_at' => 'datetime',
        'reset_at' => 'datetime',
        'row_refs' => 'array',
    ];
}
