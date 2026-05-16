<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandlordEngagementScore extends Model
{
    use TenantScope;

    protected $fillable = [
        'landlord_id',
        'day',
        'score',
        'components',
    ];

    protected $casts = [
        'day' => 'date',
        'score' => 'integer',
        'components' => 'array',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
