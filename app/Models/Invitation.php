<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'landlord_id',
        'email',
        'role',
        'target_user_id',
        'token',
        'property_id',
        'water_connection_id',
        'accepted_at',
        'viewed_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'viewed_at' => 'datetime',
    ];

    /**
     * Get the landlord who sent the invitation
     */
    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the property this invitation is for
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the target user (if invitation is for an existing user)
     */
    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Phase-95: the water connection a water_client invitation provisions.
     */
    public function waterConnection()
    {
        return $this->belongsTo(WaterConnection::class);
    }

    /**
     * Check if this invitation is for an existing user
     */
    public function isForExistingUser(): bool
    {
        return ! is_null($this->target_user_id);
    }

    /**
     * Check if invitation has been accepted
     */
    public function isAccepted(): bool
    {
        return ! is_null($this->accepted_at);
    }

    /**
     * Check if invitation has expired (30 days)
     */
    public function isExpired(): bool
    {
        return $this->created_at->addDays(30)->isPast() && ! $this->isAccepted();
    }

    /**
     * Check if invitation is valid (not accepted and not expired)
     */
    public function isValid(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    /**
     * Mark invitation as accepted
     */
    public function markAsAccepted(): void
    {
        $this->update(['accepted_at' => now()]);
    }

    /**
     * Generate a unique invitation token
     */
    public static function generateToken(): string
    {
        return \App\Support\Tokens::secure(32);
    }

    /**
     * Scope: Only pending invitations
     */
    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
            ->where('created_at', '>', Carbon::now()->subDays(30));
    }

    /**
     * Scope: Only accepted invitations
     */
    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }

    /**
     * Scope: Expired invitations
     */
    public function scopeExpired($query)
    {
        return $query->whereNull('accepted_at')
            ->where('created_at', '<=', Carbon::now()->subDays(30));
    }

    /**
     * Scope: Invitations for a specific target user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('target_user_id', $userId);
    }

    /**
     * Scope: Pending invitations for a specific target user
     */
    public function scopePendingForUser($query, int $userId)
    {
        return $query->where('target_user_id', $userId)
            ->whereNull('accepted_at')
            ->where('created_at', '>', Carbon::now()->subDays(30));
    }

    /**
     * Get the expiration date of this invitation
     */
    public function getExpiresAt(): Carbon
    {
        return $this->created_at->addDays(30);
    }
}
