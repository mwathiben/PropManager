<?php

declare(strict_types=1);

namespace Tests\Feature\Property;

use App\Services\Building\AmenityDetailService;
use App\Services\Property\ActivePropertyResolver;
use App\Services\Property\PropertyBenchmarkService;
use App\Services\Property\PropertyMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-78 CI: consolidated PROPERTY-DEPTH surface watchdog — schema, services,
 * routes, rollup command, runbook, and i18n parity in one place.
 */
class Phase78PropertyDepthSurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- AMENITY-DEPTH -----------------------------------------------------

    public function test_building_amenity_details_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('building_amenity_details'));
        $this->assertTrue(Schema::hasColumns('building_amenity_details', [
            'building_id', 'landlord_id', 'amenity_key', 'quantity', 'provider', 'account_ref', 'monthly_cost',
        ]));
    }

    public function test_amenity_detail_service_is_bound(): void
    {
        $this->assertInstanceOf(AmenityDetailService::class, app(AmenityDetailService::class));
    }

    // -- PROPERTY-METRICS / BENCHMARK --------------------------------------

    public function test_property_services_are_bound(): void
    {
        $this->assertInstanceOf(PropertyMetricsService::class, app(PropertyMetricsService::class));
        $this->assertInstanceOf(PropertyBenchmarkService::class, app(PropertyBenchmarkService::class));
        $this->assertInstanceOf(ActivePropertyResolver::class, app(ActivePropertyResolver::class));
    }

    // -- PROPERTY-SWITCH ---------------------------------------------------

    public function test_users_table_has_active_property_id(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'active_property_id'));
    }

    // -- PROPERTY-VIEW / routes -------------------------------------------

    public function test_property_routes_are_registered(): void
    {
        foreach (['properties.index', 'properties.show', 'properties.current', 'properties.switch', 'properties.benchmark'] as $name) {
            $this->assertTrue(Route::has($name), "Missing route: {$name}");
        }
    }

    // -- ROLLUP ------------------------------------------------------------

    public function test_benchmark_rollup_command_is_registered_and_exits_zero(): void
    {
        $this->artisan('property:benchmark-rollup')->assertExitCode(0);
    }

    // -- RUNBOOK -----------------------------------------------------------

    public function test_property_runbook_exists(): void
    {
        $this->assertTrue(file_exists(base_path('docs/runbooks/property.md')));
    }

    // -- I18N PARITY -------------------------------------------------------

    public function test_property_lang_namespace_parity(): void
    {
        $en = require base_path('lang/en/property.php');
        $sw = require base_path('lang/sw/property.php');
        $ar = require base_path('lang/ar/property.php');

        $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($a as $k => $v) {
                $keys = is_array($v)
                    ? [...$keys, ...$flatten($v, "{$prefix}{$k}.")]
                    : [...$keys, "{$prefix}{$k}"];
            }

            return $keys;
        };

        $enKeys = $flatten($en);
        sort($enKeys);
        $swKeys = $flatten($sw);
        sort($swKeys);
        $arKeys = $flatten($ar);
        sort($arKeys);

        $this->assertSame($enKeys, $swKeys, 'sw/property.php key drift');
        $this->assertSame($enKeys, $arKeys, 'ar/property.php key drift');
    }

    public function test_nav_keys_present_in_all_locales(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $json = json_decode(file_get_contents(base_path("lang/{$locale}.json")), true);
            $this->assertArrayHasKey('portfolio', $json['nav'], "{$locale}: nav.portfolio missing");
            $this->assertArrayHasKey('benchmark', $json['nav'], "{$locale}: nav.benchmark missing");
        }
    }
}
