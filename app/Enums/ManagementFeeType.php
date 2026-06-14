<?php

namespace App\Enums;

enum ManagementFeeType: string
{
    case None = 'none';
    case Percentage = 'percentage';
    case Flat = 'flat';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No Fee',
            self::Percentage => 'Percentage',
            self::Flat => 'Flat Amount',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
