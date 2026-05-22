<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Meter;
use App\Policies\MeterPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-86 WATER-METER-FOUNDATION surface guard: schema, routes, policy wiring
 * and i18n parity for the meter foundation. Fast structural assertions.
 */
class Phase86WaterMeterFoundationSurfaceTest extends TestCase
{
    public function test_water_meters_schema_exists(): void
    {
        $this->assertTrue(Schema::hasTable('water_meters'));
        $this->assertTrue(Schema::hasColumns('water_meters', [
            'landlord_id', 'building_id', 'unit_id', 'parent_meter_id', 'serial_number',
            'utility_type', 'meter_type', 'status', 'initial_reading', 'installed_at',
            'decommissioned_at', 'replaced_by_meter_id', 'deleted_at',
        ]));
    }

    public function test_water_readings_gained_meter_id_and_anomaly_flag(): void
    {
        $this->assertTrue(Schema::hasColumn('water_readings', 'meter_id'));
        $this->assertTrue(Schema::hasColumn('water_readings', 'is_anomalous'));
    }

    public function test_meter_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('meters.index'));
        $this->assertTrue(Route::has('meters.store'));
        $this->assertTrue(Route::has('meters.replace'));
        $this->assertTrue(Route::has('meters.decommission'));
    }

    public function test_meter_policy_is_registered(): void
    {
        $this->assertInstanceOf(MeterPolicy::class, Gate::getPolicyFor(Meter::class));
    }

    public function test_meter_lang_parity_across_locales(): void
    {
        $en = $this->flatten(require base_path('lang/en/meter.php'));
        $sw = $this->flatten(require base_path('lang/sw/meter.php'));
        $ar = $this->flatten(require base_path('lang/ar/meter.php'));

        $this->assertSame($en, $sw, 'sw meter.php keys diverge from en');
        $this->assertSame($en, $ar, 'ar meter.php keys diverge from en');
    }

    public function test_water_review_spike_keys_present_in_all_locales(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $water = require base_path("lang/{$locale}/water.php");
            $this->assertArrayHasKey('spike', $water['review'] ?? [], "{$locale} missing water.review.spike");
            $this->assertArrayHasKey('reading_failed', $water, "{$locale} missing water.reading_failed");
        }
    }

    public function test_runbook_documents_the_meter_lifecycle(): void
    {
        $runbook = file_get_contents(base_path('docs/runbooks/water.md'));
        $this->assertStringContainsStringIgnoringCase('meter', $runbook);
    }

    /**
     * @param  array<string, mixed>  $array
     * @return list<string>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $full = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flatten($value, $full));
            } else {
                $keys[] = $full;
            }
        }
        sort($keys);

        return $keys;
    }
}
