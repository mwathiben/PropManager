<?php

namespace App\Enums;

enum ManagementFeeType: string
{
    case None = 'none';
    case Percentage = 'percentage';
    case Flat = 'flat';
}
