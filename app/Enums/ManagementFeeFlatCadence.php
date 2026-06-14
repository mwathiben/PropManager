<?php

namespace App\Enums;

enum ManagementFeeFlatCadence: string
{
    case PerPeriod = 'per_period';
    case PerUnit = 'per_unit';
}
