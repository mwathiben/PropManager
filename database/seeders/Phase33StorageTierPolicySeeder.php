<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\StorageTierPolicy;
use Illuminate\Database\Seeder;

/**
 * Phase-33 COST-STORAGE-1: default lifecycle policies.
 *
 *   invoices/    -> ia  at 90d  (rare reads after a quarter)
 *   receipts/    -> ia  at 180d
 *   exports/     -> glacier at 30d (read-once dumps)
 *   audit-logs/  -> glacier at 365d (compliance only)
 */
class Phase33StorageTierPolicySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['invoices/', 90,  StorageTierPolicy::TIER_IA],
            ['receipts/', 180, StorageTierPolicy::TIER_IA],
            ['exports/',  30,  StorageTierPolicy::TIER_GLACIER],
            ['audit-logs/', 365, StorageTierPolicy::TIER_GLACIER],
        ];

        foreach ($defaults as [$prefix, $age, $tier]) {
            StorageTierPolicy::updateOrCreate(
                ['disk_name' => 's3', 'path_prefix' => $prefix],
                ['max_age_days' => $age, 'target_tier' => $tier, 'is_active' => true],
            );
        }
    }
}
