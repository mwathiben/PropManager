<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Models\NotificationPreference;

final readonly class QuietHoursConfig
{
    public function __construct(
        public bool $enabled,
        public string $start,
        public string $end,
        public string $timezone,
    ) {}

    public static function fromPreference(NotificationPreference $preference, string $timezone): self
    {
        return new self(
            enabled: (bool) $preference->quiet_hours_enabled,
            start: $preference->quiet_hours_start ?? '22:00',
            end: $preference->quiet_hours_end ?? '08:00',
            timezone: $timezone,
        );
    }

    public static function disabled(): self
    {
        return new self(
            enabled: false,
            start: '22:00',
            end: '08:00',
            timezone: 'Africa/Nairobi',
        );
    }
}
