<?php

namespace App\Enums;

enum MoveOutStatus: string
{
    case NoticeGiven = 'notice_given';
    case InspectionPending = 'inspection_pending';
    case InspectionComplete = 'inspection_complete';
    case SettlementPending = 'settlement_pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
