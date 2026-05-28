<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceSlo extends Model
{
    public const TIER_1 = 'tier1';

    public const TIER_2 = 'tier2';

    public const TIER_3 = 'tier3';

    public const TIER_4 = 'tier4';

    protected $fillable = [
        'service_key',
        'tier',
        'window_days',
        'objective_pct',
        'good_indicator_metric',
        'bad_indicator_metric',
        'is_active',
        'description',
    ];

    protected $casts = [
        'window_days' => 'integer',
        'objective_pct' => 'float',
        'is_active' => 'boolean',
    ];
}
