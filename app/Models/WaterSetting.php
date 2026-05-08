<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterSetting extends Model
{
    use HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'rate_per_unit',
        'billing_day',
        'is_enabled',
    ];

    protected $casts = [
        'rate_per_unit' => 'decimal:2',
        'billing_day' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
