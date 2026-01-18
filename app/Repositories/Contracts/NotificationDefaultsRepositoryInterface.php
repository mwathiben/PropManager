<?php

namespace App\Repositories\Contracts;

interface NotificationDefaultsRepositoryInterface
{
    /**
     * Get all notification defaults for a landlord.
     *
     * @return array{
     *     default_channels: array,
     *     type_settings: array,
     *     quiet_hours_enabled: bool,
     *     quiet_hours_start: string,
     *     quiet_hours_end: string,
     *     quiet_hours_queue_notifications: bool,
     *     max_retries: int,
     *     retry_delay_minutes: int,
     *     daily_limit_per_tenant: int,
     *     hourly_limit_per_tenant: int,
     *     sender_name: ?string,
     *     reply_to_email: ?string,
     *     archive_days: int,
     *     track_read_status: bool,
     *     reminder_days_before_due: int
     * }
     */
    public function getDefaults(int $landlordId): array;

    /**
     * Update notification defaults for a landlord (dual-write).
     */
    public function updateDefaults(int $landlordId, array $defaults): void;

    /**
     * Get quiet hours configuration.
     *
     * @return array{enabled: bool, start: string, end: string, queue_notifications: bool}
     */
    public function getQuietHours(int $landlordId): array;

    /**
     * Get rate limits for notifications.
     *
     * @return array{daily: int, hourly: int, max_retries: int, retry_delay: int}
     */
    public function getNotificationLimits(int $landlordId): array;

    /**
     * Get sender settings.
     *
     * @return array{sender_name: ?string, reply_to_email: ?string}
     */
    public function getSenderSettings(int $landlordId): array;

    /**
     * Get archive and tracking settings.
     *
     * @return array{archive_days: int, track_read_status: bool}
     */
    public function getArchiveSettings(int $landlordId): array;

    /**
     * Get default notification channels.
     *
     * @return array<string>
     */
    public function getDefaultChannels(int $landlordId): array;

    /**
     * Get reminder days before due date.
     */
    public function getReminderDays(int $landlordId): int;
}
