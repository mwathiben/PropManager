<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\WaterProductionCost;
use App\Services\Water\WaterIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-91 WATER-HUB-INTELLIGENCE surface guard: schema, service, routes, policy
 * registration, and i18n parity for the analytics tab.
 */
class Phase91WaterIntelligenceSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_exists(): void
    {
        $this->assertTrue(Schema::hasTable('water_production_costs'));
        $this->assertTrue(Schema::hasColumns('water_production_costs', ['landlord_id', 'building_id', 'cost_date', 'amount', 'category', 'note']));
    }

    public function test_service_resolves_and_returns_shape(): void
    {
        $service = app(WaterIntelligenceService::class);
        $out = $service->forLandlord(999999);

        foreach (['trend', 'summary', 'building_comparison', 'top_consumers', 'anomalies', 'non_revenue_water', 'billing', 'production', 'recent_costs'] as $key) {
            $this->assertArrayHasKey($key, $out, "intelligence payload missing {$key}");
        }
        $this->assertCount(12, $out['trend']);
        $this->assertSame([], $out['non_revenue_water']);
    }

    public function test_routes_registered(): void
    {
        $this->assertTrue(Route::has('water.production-costs.store'));
        $this->assertTrue(Route::has('water.production-costs.destroy'));
    }

    public function test_policy_registered(): void
    {
        $this->assertNotNull(\Illuminate\Support\Facades\Gate::getPolicyFor(WaterProductionCost::class));
    }

    public function test_categories_defined(): void
    {
        $this->assertSame(['electricity', 'maintenance', 'permit', 'other'], WaterProductionCost::CATEGORIES);
    }

    public function test_lang_parity(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $water = require base_path("lang/{$locale}/water.php");
            $this->assertArrayHasKey('intelligence', $water['tabs'] ?? [], "{$locale} missing water.tabs.intelligence");
            $intel = $water['intelligence'] ?? [];
            $this->assertArrayHasKey('trend_title', $intel, "{$locale} missing water.intelligence.trend_title");
            $this->assertArrayHasKey('production_title', $intel, "{$locale} missing water.intelligence.production_title");
            $this->assertArrayHasKey('kpi', $intel, "{$locale} missing water.intelligence.kpi");
            $this->assertArrayHasKey('category', $intel, "{$locale} missing water.intelligence.category");
            foreach (WaterProductionCost::CATEGORIES as $cat) {
                $this->assertArrayHasKey($cat, $intel['category'] ?? [], "{$locale} missing water.intelligence.category.{$cat}");
            }
        }
    }
}
