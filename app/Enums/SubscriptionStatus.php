<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case PastDue = 'past_due';
    case Trialing = 'trialing';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Cancelled => 'Cancelled',
            self::PastDue => 'Past Due',
            self::Trialing => 'Trial',
            self::Paused => 'Paused',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Cancelled => 'gray',
            self::PastDue => 'red',
            self::Trialing => 'blue',
            self::Paused => 'yellow',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
