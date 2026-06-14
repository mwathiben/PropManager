<?php

namespace App\Enums;

enum ManagementFeeBase: string
{
    case Collected = 'collected';
    case Billed = 'billed';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Collected => 'Collected (Cash In)',
            self::Billed => 'Billed (Invoice Total)',
            self::Scheduled => 'Scheduled (Contract Rent)',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
