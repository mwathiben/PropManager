<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PlatformFeeTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformFeeTierTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_lowest_tier_for_zero_volume(): void
    {
        $this->seedDefaultTiers();

        $tier = PlatformFeeTier::forVolume(0);

        $this->assertNotNull($tier);
        $this->assertEquals('Starter', $tier->name);
        $this->assertEquals(3.00, (float) $tier->fee_percentage);
    }

    public function test_returns_next_tier_at_exact_boundary(): void
    {
        $this->seedDefaultTiers();

        $tier = PlatformFeeTier::forVolume(50000);

        $this->assertNotNull($tier);
        $this->assertEquals('Growth', $tier->name);
        $this->assertEquals(2.50, (float) $tier->fee_percentage);
    }

    public function test_returns_highest_tier_for_large_volume(): void
    {
        $this->seedDefaultTiers();

        $tier = PlatformFeeTier::forVolume(1000000);

        $this->assertNotNull($tier);
        $this->assertEquals('Enterprise', $tier->name);
        $this->assertEquals(1.50, (float) $tier->fee_percentage);
    }

    public function test_returns_null_when_no_tiers_exist(): void
    {
        $tier = PlatformFeeTier::forVolume(5000);

        $this->assertNull($tier);
    }

    public function test_returns_correct_tier_at_mid_range(): void
    {
        $this->seedDefaultTiers();

        $this->assertEquals('Starter', PlatformFeeTier::forVolume(25000)->name);
        $this->assertEquals('Growth', PlatformFeeTier::forVolume(100000)->name);
        $this->assertEquals('Scale', PlatformFeeTier::forVolume(350000)->name);
        $this->assertEquals('Enterprise', PlatformFeeTier::forVolume(600000)->name);
    }

    public function test_ordered_scope_returns_ascending(): void
    {
        $this->seedDefaultTiers();

        $tiers = PlatformFeeTier::ordered()->pluck('name')->toArray();

        $this->assertEquals(['Starter', 'Growth', 'Scale', 'Enterprise'], $tiers);
    }

    public function test_active_scope_filters_inactive(): void
    {
        $this->seedDefaultTiers();
        PlatformFeeTier::where('name', 'Enterprise')->update(['is_active' => false]);

        $activeTiers = PlatformFeeTier::active()->pluck('name')->toArray();

        $this->assertNotContains('Enterprise', $activeTiers);
        $this->assertCount(3, $activeTiers);
    }

    private function seedDefaultTiers(): void
    {
        PlatformFeeTier::create(['name' => 'Starter', 'min_volume' => 0, 'max_volume' => 49999.99, 'fee_percentage' => 3.00, 'sort_order' => 0, 'is_active' => true]);
        PlatformFeeTier::create(['name' => 'Growth', 'min_volume' => 50000, 'max_volume' => 199999.99, 'fee_percentage' => 2.50, 'sort_order' => 1, 'is_active' => true]);
        PlatformFeeTier::create(['name' => 'Scale', 'min_volume' => 200000, 'max_volume' => 499999.99, 'fee_percentage' => 2.00, 'sort_order' => 2, 'is_active' => true]);
        PlatformFeeTier::create(['name' => 'Enterprise', 'min_volume' => 500000, 'max_volume' => null, 'fee_percentage' => 1.50, 'sort_order' => 3, 'is_active' => true]);
    }
}
