<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class NotificationDefaults extends Model
{
    use TenantScope;

    public const DEFAULT_CHANNELS = ['email'];

    public const DEFAULT_REMINDER_DAYS = 7;

    public const DEFAULT_TYPE_SETTINGS = [
        'rent_reminder' => true,
        'arrears_notice' => true,
        'invoice' => true,
        'receipt' => true,
        'rent_hike' => true,
        'lease_expiry' => true,
        'lease_renewal' => true,
        'maintenance_notice' => true,
        'general' => true,
        'eviction_notice' => true,
        'caretaker_invitation' => true,
        'tenant_invitation' => true,
    ];

    protected $fillable = [
        'landlord_id',
        'default_channels',
        'type_settings',
        'reminder_days_before_due',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected $casts = [
        'default_channels' => 'array',
        'type_settings' => 'array',
        'reminder_days_before_due' => 'integer',
        'quiet_hours_enabled' => 'boolean',
    ];

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public static function forLandlord(int $landlordId): self
    {
        return static::withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->first() ?? static::createDefault($landlordId);
    }

    public static function getOrCreate(int $landlordId): self
    {
        return static::withoutGlobalScope('landlord')->firstOrCreate(
            ['landlord_id' => $landlordId],
            static::getDefaultAttributes()
        );
    }

    public static function createDefault(int $landlordId): self
    {
        return new static(array_merge(
            ['landlord_id' => $landlordId],
            static::getDefaultAttributes()
        ));
    }

    public static function getDefaultAttributes(): array
    {
        return [
            'default_channels' => self::DEFAULT_CHANNELS,
            'type_settings' => self::DEFAULT_TYPE_SETTINGS,
            'reminder_days_before_due' => self::DEFAULT_REMINDER_DAYS,
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
        ];
    }

    public function isChannelEnabled(string $channel): bool
    {
        return in_array($channel, $this->default_channels ?? self::DEFAULT_CHANNELS, true);
    }

    public function isTypeEnabled(string $type): bool
    {
        $settings = $this->type_settings ?? self::DEFAULT_TYPE_SETTINGS;

        return $settings[$type] ?? true;
    }

    public function toPreferenceArray(): array
    {
        $channels = $this->default_channels ?? self::DEFAULT_CHANNELS;
        $types = $this->type_settings ?? self::DEFAULT_TYPE_SETTINGS;

        return [
            'email_enabled' => in_array('email', $channels, true),
            'sms_enabled' => in_array('sms', $channels, true),
            'whatsapp_enabled' => in_array('whatsapp', $channels, true),
            'push_enabled' => in_array('push', $channels, true),
            'in_app_enabled' => in_array('in_app', $channels, true) || true,
            'rent_reminder_enabled' => $types['rent_reminder'] ?? true,
            'arrears_notice_enabled' => $types['arrears_notice'] ?? true,
            'invoice_enabled' => $types['invoice'] ?? true,
            'receipt_enabled' => $types['receipt'] ?? true,
            'rent_hike_enabled' => $types['rent_hike'] ?? true,
            'lease_expiry_enabled' => $types['lease_expiry'] ?? true,
            'lease_renewal_enabled' => $types['lease_renewal'] ?? true,
            'maintenance_notice_enabled' => $types['maintenance_notice'] ?? true,
            'general_enabled' => $types['general'] ?? true,
            'eviction_notice_enabled' => $types['eviction_notice'] ?? true,
            'caretaker_invitation_enabled' => $types['caretaker_invitation'] ?? true,
            'tenant_invitation_enabled' => $types['tenant_invitation'] ?? true,
            'rent_reminder_days_before' => $this->reminder_days_before_due ?? self::DEFAULT_REMINDER_DAYS,
            'quiet_hours_enabled' => $this->quiet_hours_enabled ?? true,
            'quiet_hours_start' => $this->quiet_hours_start ?? '22:00',
            'quiet_hours_end' => $this->quiet_hours_end ?? '08:00',
        ];
    }

    public function updateChannels(array $channels): self
    {
        $validChannels = array_intersect($channels, ['email', 'sms', 'whatsapp', 'push', 'in_app']);
        $this->default_channels = array_values($validChannels);
        $this->save();

        return $this;
    }

    public function updateTypeSettings(array $settings): self
    {
        $current = $this->type_settings ?? self::DEFAULT_TYPE_SETTINGS;
        $this->type_settings = array_merge($current, $settings);
        $this->save();

        return $this;
    }
}
