<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\PlatformFeeTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class DashboardTierDisplayTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_dashboard_includes_tier_info_when_tiers_exist(): void
    {
        $setupData = $this->createLandlordWithFullSetup();

        PlatformFeeTier::create(['name' => 'Starter', 'min_volume' => 0, 'max_volume' => 49999.99, 'fee_percentage' => 3.00, 'sort_order' => 0, 'is_active' => true]);
        PlatformFeeTier::create(['name' => 'Growth', 'min_volume' => 50000, 'max_volume' => 199999.99, 'fee_percentage' => 2.50, 'sort_order' => 1, 'is_active' => true]);

        $response = $this->actingAs($setupData['landlord'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('currentTier')
            ->has('mtdVolume')
            ->has('allTiers', 2)
            ->where('currentTier.name', 'Starter')
            ->where('mtdVolume', 0)
        );
    }

    public function test_dashboard_has_null_tier_when_no_tiers_configured(): void
    {
        $setupData = $this->createLandlordWithFullSetup();

        $response = $this->actingAs($setupData['landlord'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('currentTier', null)
            ->where('mtdVolume', 0)
            ->where('allTiers', [])
        );
    }
}
