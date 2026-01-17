<?php

namespace App\Models;

use App\Traits\TenantScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        // Quiet hours settings
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
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
        // Quiet hours casts
        'quiet_hours_enabled' => 'boolean',
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
     * Validate that whatsapp_number is in E.164 format.
     * E.164 format: +[country code][number] e.g., +254712345678
     */
    public function isValidE164WhatsAppNumber(): bool
    {
        if (empty($this->whatsapp_number)) {
            return false;
        }

        return (bool) preg_match('/^\+[1-9]\d{1,14}$/', $this->whatsapp_number);
    }

    /**
     * Format a phone number to E.164 format for Kenya.
     * Converts 0712345678 or 254712345678 to +254712345678
     */
    public static function formatToE164(string $phone, string $countryCode = '254'): ?string
    {
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        if (preg_match('/^\+[1-9]\d{1,14}$/', $cleaned)) {
            return $cleaned;
        }

        $cleaned = ltrim($cleaned, '+');

        if (str_starts_with($cleaned, '0')) {
            $cleaned = $countryCode.substr($cleaned, 1);
        }

        if (str_starts_with($cleaned, $countryCode)) {
            return '+'.$cleaned;
        }

        return null;
    }

    /**
     * Mutator to automatically format whatsapp_number to E.164.
     */
    protected function whatsappNumber(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? self::formatToE164($value) : null,
        );
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

    /**
     * Check if the current time falls within quiet hours.
     */
    public function isInQuietHours(Carbon $now): bool
    {
        if (! $this->quiet_hours_enabled) {
            return false;
        }

        $start = Carbon::parse($this->quiet_hours_start ?? '22:00', $now->timezone);
        $end = Carbon::parse($this->quiet_hours_end ?? '08:00', $now->timezone);

        $start->setDate($now->year, $now->month, $now->day);
        $end->setDate($now->year, $now->month, $now->day);

        if ($end->lessThan($start)) {
            return $now->greaterThanOrEqualTo($start) || $now->lessThan($end);
        }

        return $now->between($start, $end);
    }

    /**
     * Get the next quiet hours end time from now.
     */
    public function getQuietHoursEnd(string $timezone): Carbon
    {
        $now = Carbon::now($timezone);
        $end = Carbon::parse($this->quiet_hours_end ?? '08:00', $timezone);
        $end->setDate($now->year, $now->month, $now->day);

        if ($end->lessThanOrEqualTo($now)) {
            $end->addDay();
        }

        return $end;
    }
}
