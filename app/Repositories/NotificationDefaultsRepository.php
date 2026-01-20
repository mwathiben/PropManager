<?php

namespace App\Repositories;

use App\Models\NotificationDefaults;
use App\Repositories\Contracts\NotificationDefaultsRepositoryInterface;

class NotificationDefaultsRepository implements NotificationDefaultsRepositoryInterface
{
    private const DEFAULT_QUIET_HOURS_START = '22:00';

    private const DEFAULT_QUIET_HOURS_END = '08:00';

    private const DEFAULT_MAX_RETRIES = 3;

    private const DEFAULT_RETRY_DELAY = 5;

    private const DEFAULT_DAILY_LIMIT = 20;

    private const DEFAULT_HOURLY_LIMIT = 5;

    private const DEFAULT_ARCHIVE_DAYS = 90;

    private const DEFAULT_REMINDER_DAYS = 7;

    public function getDefaults(int $landlordId): array
    {
        $defaults = NotificationDefaults::forLandlord($landlordId);

        return [
            'default_channels' => $defaults->default_channels ?? NotificationDefaults::DEFAULT_CHANNELS,
            'type_settings' => $defaults->type_settings ?? NotificationDefaults::DEFAULT_TYPE_SETTINGS,
            'quiet_hours_enabled' => $defaults->quiet_hours_enabled ?? true,
            'quiet_hours_start' => $defaults->quiet_hours_start ?? self::DEFAULT_QUIET_HOURS_START,
            'quiet_hours_end' => $defaults->quiet_hours_end ?? self::DEFAULT_QUIET_HOURS_END,
            'quiet_hours_queue_notifications' => $defaults->quiet_hours_queue_notifications ?? true,
            'max_retries' => $defaults->max_retries ?? self::DEFAULT_MAX_RETRIES,
            'retry_delay_minutes' => $defaults->retry_delay_minutes ?? self::DEFAULT_RETRY_DELAY,
            'daily_limit_per_tenant' => $defaults->daily_limit_per_tenant ?? self::DEFAULT_DAILY_LIMIT,
            'hourly_limit_per_tenant' => $defaults->hourly_limit_per_tenant ?? self::DEFAULT_HOURLY_LIMIT,
            'sender_name' => $defaults->sender_name,
            'reply_to_email' => $defaults->reply_to_email,
            'archive_days' => $defaults->archive_days ?? self::DEFAULT_ARCHIVE_DAYS,
            'track_read_status' => $defaults->track_read_status ?? true,
            'reminder_days_before_due' => $defaults->reminder_days_before_due ?? self::DEFAULT_REMINDER_DAYS,
        ];
    }

    public function updateDefaults(int $landlordId, array $defaults): void
    {
        $model = NotificationDefaults::getOrCreate($landlordId);
        $updateData = [];

        if (isset($defaults['default_channels'])) {
            $updateData['default_channels'] = $defaults['default_channels'];
        }
        if (isset($defaults['type_settings'])) {
            $updateData['type_settings'] = $defaults['type_settings'];
        }
        if (isset($defaults['quiet_hours_enabled'])) {
            $updateData['quiet_hours_enabled'] = $defaults['quiet_hours_enabled'];
        }
        if (isset($defaults['quiet_hours_start'])) {
            $updateData['quiet_hours_start'] = $defaults['quiet_hours_start'];
        }
        if (isset($defaults['quiet_hours_end'])) {
            $updateData['quiet_hours_end'] = $defaults['quiet_hours_end'];
        }
        if (isset($defaults['quiet_hours_queue_notifications'])) {
            $updateData['quiet_hours_queue_notifications'] = $defaults['quiet_hours_queue_notifications'];
        }
        if (isset($defaults['max_retries'])) {
            $updateData['max_retries'] = $defaults['max_retries'];
        }
        if (isset($defaults['retry_delay_minutes'])) {
            $updateData['retry_delay_minutes'] = $defaults['retry_delay_minutes'];
        }
        if (isset($defaults['daily_limit_per_tenant'])) {
            $updateData['daily_limit_per_tenant'] = $defaults['daily_limit_per_tenant'];
        }
        if (isset($defaults['hourly_limit_per_tenant'])) {
            $updateData['hourly_limit_per_tenant'] = $defaults['hourly_limit_per_tenant'];
        }
        if (array_key_exists('sender_name', $defaults)) {
            $updateData['sender_name'] = $defaults['sender_name'];
        }
        if (array_key_exists('reply_to_email', $defaults)) {
            $updateData['reply_to_email'] = $defaults['reply_to_email'];
        }
        if (isset($defaults['archive_days'])) {
            $updateData['archive_days'] = $defaults['archive_days'];
        }
        if (isset($defaults['track_read_status'])) {
            $updateData['track_read_status'] = $defaults['track_read_status'];
        }
        if (isset($defaults['reminder_days_before_due'])) {
            $updateData['reminder_days_before_due'] = $defaults['reminder_days_before_due'];
        }

        if (! empty($updateData)) {
            $model->update($updateData);
        }
    }

    public function getQuietHours(int $landlordId): array
    {
        $defaults = NotificationDefaults::forLandlord($landlordId);

        return [
            'enabled' => $defaults->quiet_hours_enabled ?? true,
            'start' => $defaults->quiet_hours_start ?? self::DEFAULT_QUIET_HOURS_START,
            'end' => $defaults->quiet_hours_end ?? self::DEFAULT_QUIET_HOURS_END,
            'queue_notifications' => $defaults->quiet_hours_queue_notifications ?? true,
        ];
    }

    public function getNotificationLimits(int $landlordId): array
    {
        $defaults = NotificationDefaults::forLandlord($landlordId);

        return [
            'daily' => $defaults->daily_limit_per_tenant ?? self::DEFAULT_DAILY_LIMIT,
            'hourly' => $defaults->hourly_limit_per_tenant ?? self::DEFAULT_HOURLY_LIMIT,
            'max_retries' => $defaults->max_retries ?? self::DEFAULT_MAX_RETRIES,
            'retry_delay' => $defaults->retry_delay_minutes ?? self::DEFAULT_RETRY_DELAY,
        ];
    }

    public function getSenderSettings(int $landlordId): array
    {
        $defaults = NotificationDefaults::forLandlord($landlordId);

        return [
            'sender_name' => $defaults->sender_name,
            'reply_to_email' => $defaults->reply_to_email,
        ];
    }

    public function getArchiveSettings(int $landlordId): array
    {
        $defaults = NotificationDefaults::forLandlord($landlordId);

        return [
            'archive_days' => $defaults->archive_days ?? self::DEFAULT_ARCHIVE_DAYS,
            'track_read_status' => $defaults->track_read_status ?? true,
        ];
    }

    public function getDefaultChannels(int $landlordId): array
    {
        $defaults = NotificationDefaults::forLandlord($landlordId);

        return $defaults->default_channels ?? NotificationDefaults::DEFAULT_CHANNELS;
    }

    public function getReminderDays(int $landlordId): int
    {
        $defaults = NotificationDefaults::forLandlord($landlordId);

        return $defaults->reminder_days_before_due ?? self::DEFAULT_REMINDER_DAYS;
    }
}
