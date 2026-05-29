<?php

namespace App\Models;

use App\Models\Concerns\RowVersion;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    use RowVersion;
    use TenantScope;

    protected $fillable = [
        'version',
        'landlord_id',
        'ticket_id',
        'user_id',
        'comment',
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    // --- RELATIONSHIPS ---

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // --- SCOPES ---

    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    // --- HELPERS ---

    public function isInternal(): bool
    {
        return $this->is_internal;
    }

    public function isPublic(): bool
    {
        return ! $this->is_internal;
    }

    public function canBeSeenBy(User $user): bool
    {
        // Tenants can only see public comments
        if ($user->isTenant()) {
            return $this->isPublic();
        }

        // Landlords and caretakers can see all comments
        return true;
    }
}
