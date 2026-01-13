<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantNote extends Model
{
    use TenantScope;

    protected $fillable = [
        'landlord_id',
        'tenant_id',
        'content',
        'created_by',
        'is_pinned',
    ];

    /**
     * Get the landlord
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the tenant this note is about
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who created this note
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
