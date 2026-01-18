<?php

namespace App\Repositories;

use App\Models\NotificationDefaults;
use App\Models\Setting;
use App\Repositories\Contracts\NotificationDefaultsRepositoryInterface;

class DualWriteNotificationDefaultsRepository implements NotificationDefaultsRepositoryInterface
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
        if (config('features.notification_v2')) {
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

        return $this->getDefaultsFromLegacy($landlordId);
    }

    public function updateDefaults(int $landlordId, array $defaults): void
    {
        // Legacy Setting writes
        $this->writeLegacyDefaults($landlordId, $defaults);

        // New NotificationDefaults write
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
        if (config('features.notification_v2')) {
            $defaults = NotificationDefaults::forLandlord($landlordId);

            return [
                'enabled' => $defaults->quiet_hours_enabled ?? true,
                'start' => $defaults->quiet_hours_start ?? self::DEFAULT_QUIET_HOURS_START,
                'end' => $defaults->quiet_hours_end ?? self::DEFAULT_QUIET_HOURS_END,
                'queue_notifications' => $defaults->quiet_hours_queue_notifications ?? true,
            ];
        }

        return [
            'enabled' => (bool) Setting::get('quiet_hours_enabled', true, $landlordId),
            'start' => Setting::get('quiet_hours_start', self::DEFAULT_QUIET_HOURS_START, $landlordId),
            'end' => Setting::get('quiet_hours_end', self::DEFAULT_QUIET_HOURS_END, $landlordId),
            'queue_notifications' => (bool) Setting::get('quiet_hours_queue_notifications', true, $landlordId),
        ];
    }

    public function getNotificationLimits(int $landlordId): array
    {
        if (config('features.notification_v2')) {
            $defaults = NotificationDefaults::forLandlord($landlordId);

            return [
                'daily' => $defaults->daily_limit_per_tenant ?? self::DEFAULT_DAILY_LIMIT,
                'hourly' => $defaults->hourly_limit_per_tenant ?? self::DEFAULT_HOURLY_LIMIT,
                'max_retries' => $defaults->max_retries ?? self::DEFAULT_MAX_RETRIES,
                'retry_delay' => $defaults->retry_delay_minutes ?? self::DEFAULT_RETRY_DELAY,
            ];
        }

        return [
            'daily' => (int) Setting::get('notification_daily_limit_per_tenant', self::DEFAULT_DAILY_LIMIT, $landlordId),
            'hourly' => (int) Setting::get('notification_hourly_limit_per_tenant', self::DEFAULT_HOURLY_LIMIT, $landlordId),
            'max_retries' => (int) Setting::get('notification_max_retries', self::DEFAULT_MAX_RETRIES, $landlordId),
            'retry_delay' => (int) Setting::get('notification_retry_delay', self::DEFAULT_RETRY_DELAY, $landlordId),
        ];
    }

    public function getSenderSettings(int $landlordId): array
    {
        if (config('features.notification_v2')) {
            $defaults = NotificationDefaults::forLandlord($landlordId);

            return [
                'sender_name' => $defaults->sender_name,
                'reply_to_email' => $defaults->reply_to_email,
            ];
        }

        return [
            'sender_name' => Setting::get('notification_sender_name', null, $landlordId),
            'reply_to_email' => Setting::get('notification_reply_to_email', null, $landlordId),
        ];
    }

    public function getArchiveSettings(int $landlordId): array
    {
        if (config('features.notification_v2')) {
            $defaults = NotificationDefaults::forLandlord($landlordId);

            return [
                'archive_days' => $defaults->archive_days ?? self::DEFAULT_ARCHIVE_DAYS,
                'track_read_status' => $defaults->track_read_status ?? true,
            ];
        }

        return [
            'archive_days' => (int) Setting::get('notification_archive_days', self::DEFAULT_ARCHIVE_DAYS, $landlordId),
            'track_read_status' => (bool) Setting::get('notification_track_read_status', true, $landlordId),
        ];
    }

    public function getDefaultChannels(int $landlordId): array
    {
        if (config('features.notification_v2')) {
            $defaults = NotificationDefaults::forLandlord($landlordId);

            return $defaults->default_channels ?? NotificationDefaults::DEFAULT_CHANNELS;
        }

        $json = Setting::get('default_notification_channels', '["email"]', $landlordId);

        return is_array($json) ? $json : json_decode($json, true) ?? ['email'];
    }

    public function getReminderDays(int $landlordId): int
    {
        if (config('features.notification_v2')) {
            $defaults = NotificationDefaults::forLandlord($landlordId);

            return $defaults->reminder_days_before_due ?? self::DEFAULT_REMINDER_DAYS;
        }

        return (int) Setting::get('default_rent_reminder_days', self::DEFAULT_REMINDER_DAYS, $landlordId);
    }

    private function getDefaultsFromLegacy(int $landlordId): array
    {
        $channelsJson = Setting::get('default_notification_channels', '["email"]', $landlordId);
        $channels = is_array($channelsJson) ? $channelsJson : json_decode($channelsJson, true) ?? ['email'];

        return [
            'default_channels' => $channels,
            'type_settings' => NotificationDefaults::DEFAULT_TYPE_SETTINGS,
            'quiet_hours_enabled' => (bool) Setting::get('quiet_hours_enabled', true, $landlordId),
            'quiet_hours_start' => Setting::get('quiet_hours_start', self::DEFAULT_QUIET_HOURS_START, $landlordId),
            'quiet_hours_end' => Setting::get('quiet_hours_end', self::DEFAULT_QUIET_HOURS_END, $landlordId),
            'quiet_hours_queue_notifications' => (bool) Setting::get('quiet_hours_queue_notifications', true, $landlordId),
            'max_retries' => (int) Setting::get('notification_max_retries', self::DEFAULT_MAX_RETRIES, $landlordId),
            'retry_delay_minutes' => (int) Setting::get('notification_retry_delay', self::DEFAULT_RETRY_DELAY, $landlordId),
            'daily_limit_per_tenant' => (int) Setting::get('notification_daily_limit_per_tenant', self::DEFAULT_DAILY_LIMIT, $landlordId),
            'hourly_limit_per_tenant' => (int) Setting::get('notification_hourly_limit_per_tenant', self::DEFAULT_HOURLY_LIMIT, $landlordId),
            'sender_name' => Setting::get('notification_sender_name', null, $landlordId),
            'reply_to_email' => Setting::get('notification_reply_to_email', null, $landlordId),
            'archive_days' => (int) Setting::get('notification_archive_days', self::DEFAULT_ARCHIVE_DAYS, $landlordId),
            'track_read_status' => (bool) Setting::get('notification_track_read_status', true, $landlordId),
            'reminder_days_before_due' => (int) Setting::get('default_rent_reminder_days', self::DEFAULT_REMINDER_DAYS, $landlordId),
        ];
    }

    private function writeLegacyDefaults(int $landlordId, array $defaults): void
    {
        $settingsMap = [
            'quiet_hours_enabled' => ['key' => 'quiet_hours_enabled', 'label' => 'Quiet Hours Enabled'],
            'quiet_hours_start' => ['key' => 'quiet_hours_start', 'label' => 'Quiet Hours Start'],
            'quiet_hours_end' => ['key' => 'quiet_hours_end', 'label' => 'Quiet Hours End'],
            'quiet_hours_queue_notifications' => ['key' => 'quiet_hours_queue_notifications', 'label' => 'Queue During Quiet Hours'],
            'max_retries' => ['key' => 'notification_max_retries', 'label' => 'Max Retries'],
            'retry_delay_minutes' => ['key' => 'notification_retry_delay', 'label' => 'Retry Delay (minutes)'],
            'daily_limit_per_tenant' => ['key' => 'notification_daily_limit_per_tenant', 'label' => 'Daily Limit Per Tenant'],
            'hourly_limit_per_tenant' => ['key' => 'notification_hourly_limit_per_tenant', 'label' => 'Hourly Limit Per Tenant'],
            'sender_name' => ['key' => 'notification_sender_name', 'label' => 'Sender Name'],
            'reply_to_email' => ['key' => 'notification_reply_to_email', 'label' => 'Reply-To Email'],
            'archive_days' => ['key' => 'notification_archive_days', 'label' => 'Archive Days'],
            'track_read_status' => ['key' => 'notification_track_read_status', 'label' => 'Track Read Status'],
            'reminder_days_before_due' => ['key' => 'default_rent_reminder_days', 'label' => 'Default Rent Reminder Days'],
        ];

        foreach ($settingsMap as $inputKey => $config) {
            if (array_key_exists($inputKey, $defaults)) {
                Setting::set(
                    $config['key'],
                    $defaults[$inputKey],
                    false,
                    'notifications_global',
                    $config['label'],
                    $landlordId
                );
            }
        }

        if (isset($defaults['default_channels'])) {
            Setting::set(
                'default_notification_channels',
                json_encode($defaults['default_channels']),
                false,
                'notifications_global',
                'Default Channels',
                $landlordId
            );
        }
    }
}
