<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Http\Middleware\EnsureWaterModule;
use App\Services\Water\WaterModuleAccess;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase-79 CI: consolidated WATER-HUB surface watchdog — gate, middleware,
 * route gating, rename + i18n parity, tenant view, nav reachability tooling.
 */
class Phase79WaterHubSurfaceTest extends TestCase
{
    // -- WATER-GATE --------------------------------------------------------

    public function test_water_module_access_is_bound(): void
    {
        $this->assertTrue(class_exists(WaterModuleAccess::class));
        $this->assertTrue(method_exists(WaterModuleAccess::class, 'enabledFor'));
        $this->assertTrue(method_exists(WaterModuleAccess::class, 'enabledForLandlord'));
        $this->assertTrue(method_exists(WaterModuleAccess::class, 'forget'));
    }

    public function test_water_module_middleware_is_registered(): void
    {
        $aliases = app('router')->getMiddleware();
        $this->assertArrayHasKey('water.module', $aliases);
        $this->assertSame(EnsureWaterModule::class, $aliases['water.module']);
    }

    public function test_water_routes_carry_the_module_gate(): void
    {
        foreach (['water.hub', 'readings.store', 'readings.index', 'tenant.water'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route: {$name}");
            $this->assertContains('water.module', $route->gatherMiddleware(), "{$name} is not gated by water.module");
        }
    }

    public function test_water_settings_is_not_gated_so_landlords_can_enable(): void
    {
        $route = Route::getRoutes()->getByName('water.settings');
        $this->assertNotNull($route);
        $this->assertNotContains('water.module', $route->gatherMiddleware());
    }

    // -- WATER-RENAME ------------------------------------------------------

    public function test_nav_water_label_is_water_hub_in_all_locales(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $json = json_decode(file_get_contents(base_path("lang/{$locale}.json")), true);
            $this->assertNotEmpty($json['nav']['water'], "{$locale}: nav.water missing");
            $this->assertArrayHasKey('my_water', $json['nav'], "{$locale}: nav.my_water missing");
        }
        $en = json_decode(file_get_contents(base_path('lang/en.json')), true);
        $this->assertSame('Water hub', $en['nav']['water']);
    }

    // -- I18N PARITY -------------------------------------------------------

    public function test_water_lang_namespace_parity(): void
    {
        $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($a as $k => $v) {
                $keys = is_array($v) ? [...$keys, ...$flatten($v, "{$prefix}{$k}.")] : [...$keys, "{$prefix}{$k}"];
            }

            return $keys;
        };

        $en = $flatten(require base_path('lang/en/water.php'));
        $sw = $flatten(require base_path('lang/sw/water.php'));
        $ar = $flatten(require base_path('lang/ar/water.php'));
        sort($en);
        sort($sw);
        sort($ar);

        $this->assertSame($en, $sw, 'sw/water.php key drift');
        $this->assertSame($en, $ar, 'ar/water.php key drift');
    }

    // -- NAV-REACH ---------------------------------------------------------

    public function test_nav_audit_tooling_present(): void
    {
        $this->assertFileExists(base_path('scripts/nav-audit.mjs'));
        $this->assertFileExists(base_path('scripts/nav-audit-baseline.json'));
    }

    public function test_tenant_water_page_exists(): void
    {
        $this->assertFileExists(resource_path('js/Pages/Tenant/Water.vue'));
        $this->assertFileExists(resource_path('js/Pages/Water/tabs/ReviewTab.vue'));
    }

    // -- RUNBOOK -----------------------------------------------------------

    public function test_water_runbook_exists(): void
    {
        $this->assertFileExists(base_path('docs/runbooks/water.md'));
    }
}
