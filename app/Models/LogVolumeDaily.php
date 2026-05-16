<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogVolumeDaily extends Model
{
    use TenantScope;

    protected $table = 'log_volume_daily';

    protected $fillable = [
        'landlord_id',
        'day',
        'byte_count',
        'line_count',
    ];

    protected $casts = [
        'day' => 'date',
        'byte_count' => 'integer',
        'line_count' => 'integer',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
