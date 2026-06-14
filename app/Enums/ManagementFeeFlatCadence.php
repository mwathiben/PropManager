<?php

namespace App\Enums;

enum ManagementFeeFlatCadence: string
{
    case PerPeriod = 'per_period';
    case PerUnit = 'per_unit';

    public function label(): string
    {
        return match ($this) {
            self::PerPeriod => 'Per Period (Fixed)',
            self::PerUnit => 'Per Occupied Unit',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
