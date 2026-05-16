<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-35 PLATFORM-ANALYTICS-1: append-only event ledger.
 *
 * Never UPDATE rows — events are immutable once recorded. updated_at
 * disabled; only created_at exists on the table.
 */
class ProductEvent extends Model
{
    use TenantScope;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'landlord_id',
        'event_name',
        'properties',
        'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
