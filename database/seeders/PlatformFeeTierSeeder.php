<?php

namespace Database\Seeders;

use App\Models\PlatformFeeTier;
use Illuminate\Database\Seeder;

class PlatformFeeTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['name' => 'Starter', 'min_volume' => 0, 'max_volume' => 50000, 'fee_percentage' => 3.00, 'sort_order' => 0],
            ['name' => 'Growth', 'min_volume' => 50000, 'max_volume' => 200000, 'fee_percentage' => 2.50, 'sort_order' => 1],
            ['name' => 'Scale', 'min_volume' => 200000, 'max_volume' => 500000, 'fee_percentage' => 2.00, 'sort_order' => 2],
            ['name' => 'Enterprise', 'min_volume' => 500000, 'max_volume' => null, 'fee_percentage' => 1.50, 'sort_order' => 3],
        ];

        foreach ($tiers as $tier) {
            PlatformFeeTier::updateOrCreate(
                ['name' => $tier['name']],
                array_merge($tier, ['is_active' => true]),
            );
        }
    }
}
