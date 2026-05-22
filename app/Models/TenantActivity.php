<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantActivity extends Model
{
    use TenantScope;

    protected $fillable = [
        'landlord_id',
        'tenant_id',
        'type',
        'description',
        'metadata',
        'performed_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Activity types
     */
    const TYPE_LEASE_CREATED = 'lease_created';

    const TYPE_LEASE_RENEWED = 'lease_renewed';

    const TYPE_LEASE_TERMINATED = 'lease_terminated';

    const TYPE_RENT_ADJUSTED = 'rent_adjusted';

    const TYPE_PAYMENT_RECEIVED = 'payment_received';

    const TYPE_INVOICE_GENERATED = 'invoice_generated';

    // Phase-88: a pending water reading auto-approved when the review window closed.
    const TYPE_WATER_READING_AUTO_APPROVED = 'water_reading_auto_approved';

    // Phase-90: water service disconnected/reconnected for non-payment.
    const TYPE_WATER_METER_DISCONNECTED = 'water_meter_disconnected';

    const TYPE_WATER_METER_RECONNECTED = 'water_meter_reconnected';

    const TYPE_DOCUMENT_UPLOADED = 'document_uploaded';

    const TYPE_VERIFICATION_SUBMITTED = 'verification_submitted';

    const TYPE_VERIFICATION_APPROVED = 'verification_approved';

    const TYPE_VERIFICATION_REJECTED = 'verification_rejected';

    const TYPE_MOVE_OUT_INITIATED = 'move_out_initiated';

    const TYPE_MOVE_OUT_COMPLETED = 'move_out_completed';

    const TYPE_NOTE_ADDED = 'note_added';

    const TYPE_PROFILE_UPDATED = 'profile_updated';

    const TYPE_EMERGENCY_CONTACT_ADDED = 'emergency_contact_added';

    /**
     * Get the landlord
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the tenant this activity is for
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Get the user who performed this activity
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Log an activity for a tenant
     */
    public static function log(int $tenantId, string $type, string $description, array $metadata = []): self
    {
        return self::create([
            'tenant_id' => $tenantId,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
            'performed_by' => auth()->id(),
        ]);
    }

    /**
     * Scope: Filter by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: For a specific tenant
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Recent activities
     */
    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
