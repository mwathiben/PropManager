<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use TenantScope;

    protected $fillable = [
        'user_id',
        'landlord_id',
        // Notification type preferences
        'rent_reminder_enabled',
        'arrears_notice_enabled',
        'invoice_enabled',
        'receipt_enabled',
        'rent_hike_enabled',
        'lease_expiry_enabled',
        'lease_renewal_enabled',
        'maintenance_notice_enabled',
        'general_enabled',
        'eviction_notice_enabled',
        'caretaker_invitation_enabled',
        'tenant_invitation_enabled',
        // Channel preferences
        'email_enabled',
        'sms_enabled',
        'whatsapp_enabled',
        'push_enabled',
        'in_app_enabled',
        // Other settings
        'rent_reminder_days_before',
        'preferred_time',
        'whatsapp_number',
    ];

    protected $casts = [
        // Notification type casts
        'rent_reminder_enabled' => 'boolean',
        'arrears_notice_enabled' => 'boolean',
        'invoice_enabled' => 'boolean',
        'receipt_enabled' => 'boolean',
        'rent_hike_enabled' => 'boolean',
        'lease_expiry_enabled' => 'boolean',
        'lease_renewal_enabled' => 'boolean',
        'maintenance_notice_enabled' => 'boolean',
        'general_enabled' => 'boolean',
        'eviction_notice_enabled' => 'boolean',
        'caretaker_invitation_enabled' => 'boolean',
        'tenant_invitation_enabled' => 'boolean',
        // Channel casts
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        // Other casts
        'rent_reminder_days_before' => 'integer',
    ];

    /**
     * Get the user these preferences belong to
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the landlord associated with these preferences
     */
    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Check if a notification type is enabled
     */
    public function isTypeEnabled(string $type): bool
    {
        $field = $type.'_enabled';

        return $this->{$field} ?? false;
    }

    /**
     * Check if a channel is enabled
     */
    public function isChannelEnabled(string $channel): bool
    {
        $field = $channel.'_enabled';

        return $this->{$field} ?? false;
    }

    /**
     * Check if notifications can be sent via specific channel for specific type
     */
    public function canReceive(string $type, string $channel): bool
    {
        return $this->isTypeEnabled($type) && $this->isChannelEnabled($channel);
    }

    /**
     * Get or create preferences for a user
     */
    public static function getOrCreate(int $userId, int $landlordId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'landlord_id' => $landlordId],
            [
                // Default values already set in migration
            ]
        );
    }
}
