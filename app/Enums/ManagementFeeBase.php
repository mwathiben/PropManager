<?php

namespace App\Enums;

enum ManagementFeeBase: string
{
    case Collected = 'collected';
    case Billed = 'billed';
    case Scheduled = 'scheduled';
}
