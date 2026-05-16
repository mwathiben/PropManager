<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class RehydratedProductEvent extends Model
{
    use TenantScope;

    public $timestamps = false;

    protected $fillable = [
        'original_id',
        'user_id',
        'landlord_id',
        'event_name',
        'properties',
        'original_created_at',
        'rehydrated_at',
        'source_path',
    ];

    protected $casts = [
        'properties' => 'array',
        'original_created_at' => 'datetime',
        'rehydrated_at' => 'datetime',
    ];
}
