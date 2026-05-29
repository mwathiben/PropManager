<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase-33 COST-STORAGE-1: per-disk lifecycle policy. NO TenantScope —
 * this is a platform-level config table, not per-landlord data.
 *
 * Tiers mirror S3:
 *   - standard: STANDARD storage class (hot)
 *   - ia:       STANDARD_IA (warm, accessed < 1x/mo)
 *   - glacier:  GLACIER (cold, retrievable in minutes-hours)
 *   - deep_archive: GLACIER_DEEP_ARCHIVE (frozen, 12h retrieval)
 */
class StorageTierPolicy extends Model
{
    use HasFactory;

    public const TIER_STANDARD = 'standard';

    public const TIER_IA = 'ia';

    public const TIER_GLACIER = 'glacier';

    public const TIER_DEEP_ARCHIVE = 'deep_archive';

    public const TIERS = [
        self::TIER_STANDARD,
        self::TIER_IA,
        self::TIER_GLACIER,
        self::TIER_DEEP_ARCHIVE,
    ];

    protected $fillable = [
        'disk_name',
        'path_prefix',
        'max_age_days',
        'target_tier',
        'is_active',
    ];

    protected $casts = [
        'max_age_days' => 'integer',
        'is_active' => 'boolean',
    ];
}
