<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Services\Water\WaterAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase-93 WATER-TENANT-SELFSERVICE surface guard: service shape, shared
 * components present, route, lang parity.
 */
class Phase93WaterSelfServiceSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_resolves_and_returns_shape(): void
    {
        $out = app(WaterAccountService::class)->overview(999999, null);

        foreach (['history', 'summary', 'alert', 'charges'] as $key) {
            $this->assertArrayHasKey($key, $out, "account payload missing {$key}");
        }
        $this->assertCount(12, $out['history']);
        $this->assertSame([], $out['charges']);
        $this->assertNull($out['alert']);
    }

    public function test_shared_components_present(): void
    {
        $dir = resource_path('js/Components/Water');
        foreach (['WaterDisconnectionBanner', 'WaterUsageAlert', 'WaterConsumptionCard', 'WaterChargesCard'] as $component) {
            $this->assertFileExists("{$dir}/{$component}.vue", "missing shared component {$component}");
        }
    }

    public function test_route_registered(): void
    {
        $this->assertTrue(Route::has('tenant.water'));
    }

    public function test_lang_parity(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $water = require base_path("lang/{$locale}/water.php");
            $account = $water['account'] ?? [];
            foreach (['alert_title', 'alert_body', 'history_title', 'summary_latest', 'charges_title', 'no_charges'] as $key) {
                $this->assertArrayHasKey($key, $account, "{$locale} missing water.account.{$key}");
            }
        }
    }
}
