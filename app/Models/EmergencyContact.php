<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyContact extends Model
{
    use TenantScope;

    protected $fillable = [
        'landlord_id',
        'tenant_id',
        'name',
        'relationship',
        'phone',
        'email',
        'address',
        'is_primary',
        'verified_at',
        'verification_attempts_24h',
        'last_otp_sent_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
        'last_otp_sent_at' => 'datetime',
        'verification_attempts_24h' => 'integer',
    ];

    /**
     * Phase-45 EMERGENCY-CONTACT-SMS-3: maintain the
     * users.emergency_contact_* mirror automatically — when a row
     * marked is_primary=true is saved (or transitions to is_primary),
     * write {name, phone} into the user record + clear is_primary on
     * sibling rows so a tenant only has one primary contact.
     */
    protected static function booted(): void
    {
        static::saving(function (EmergencyContact $contact): void {
            if (! $contact->is_primary) {
                return;
            }
            // Clear is_primary on any other rows for this tenant.
            static::query()
                ->where('tenant_id', $contact->tenant_id)
                ->where('id', '!=', $contact->id ?? 0)
                ->update(['is_primary' => false]);
        });

        static::saved(function (EmergencyContact $contact): void {
            if (! $contact->is_primary) {
                return;
            }
            User::query()->where('id', $contact->tenant_id)->update([
                'emergency_contact_name' => $contact->name,
                'emergency_contact_phone' => $contact->phone,
            ]);
        });
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Get the landlord
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the tenant this contact is for
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Scope: Primary contacts only
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope: For a specific tenant
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
