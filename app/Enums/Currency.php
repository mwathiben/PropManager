<?php

namespace App\Enums;

enum Currency: string
{
    case KES = 'KES';
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';

    public function label(): string
    {
        return match ($this) {
            self::KES => 'Kenyan Shilling',
            self::USD => 'US Dollar',
            self::EUR => 'Euro',
            self::GBP => 'British Pound',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::KES => 'KSh',
            self::USD => '$',
            self::EUR => '€',
            self::GBP => '£',
        };
    }

    public function country(): string
    {
        return match ($this) {
            self::KES => 'Kenya',
            self::USD => 'United States',
            self::EUR => 'Eurozone',
            self::GBP => 'United Kingdom',
        };
    }

    public function locale(): string
    {
        return match ($this) {
            self::KES => 'en-KE',
            self::USD => 'en-US',
            self::EUR => 'de-DE',
            self::GBP => 'en-GB',
        };
    }

    public function minorUnitMultiplier(): int
    {
        return 100;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => "{$case->value} - {$case->label()} ({$case->country()})",
        ])->all();
    }

    public static function default(): self
    {
        return self::KES;
    }
}
