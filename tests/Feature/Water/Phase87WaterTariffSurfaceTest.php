<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Services\Water\WaterTariffService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-87 WATER-TARIFF-ENGINE surface guard: tariff config schema, service
 * binding, and i18n parity for the new settings fields.
 */
class Phase87WaterTariffSurfaceTest extends TestCase
{
    private array $columns = [
        'tiered_tariffs', 'water_standing_charge', 'water_minimum_charge',
        'water_sewerage_percent', 'water_vat_percent', 'water_source',
    ];

    public function test_tariff_columns_exist_on_payment_configurations(): void
    {
        $this->assertTrue(Schema::hasColumns('payment_configurations', $this->columns));
    }

    public function test_tariff_columns_exist_on_buildings(): void
    {
        $this->assertTrue(Schema::hasColumns('buildings', $this->columns));
    }

    public function test_water_tariff_service_is_resolvable(): void
    {
        $this->assertInstanceOf(WaterTariffService::class, app(WaterTariffService::class));
    }

    public function test_settings_lang_parity_across_locales(): void
    {
        // 2026-05-28: form-body keyspace moved from water.settings.*
        // (lang/{locale}/water.php) to a dedicated water_settings_form.*
        // namespace (lang/{locale}/water_settings_form.php) — readers
        // updated in lockstep.
        $en = $this->settingsKeys('en');
        $sw = $this->settingsKeys('sw');
        $ar = $this->settingsKeys('ar');

        $this->assertSame($en, $sw, 'sw water_settings_form keys diverge from en');
        $this->assertSame($en, $ar, 'ar water_settings_form keys diverge from en');
        // The Phase-87 keys are present.
        foreach (['tiers_title', 'standing_charge', 'sewerage_percent', 'vat_percent', 'water_source'] as $key) {
            $this->assertContains($key, $en, "missing water_settings_form.{$key}");
        }
    }

    public function test_runbook_documents_tariffs(): void
    {
        $runbook = file_get_contents(base_path('docs/runbooks/water.md'));
        $this->assertStringContainsStringIgnoringCase('tariff', $runbook);
    }

    /**
     * @return list<string>
     */
    private function settingsKeys(string $locale): array
    {
        $form = require base_path("lang/{$locale}/water_settings_form.php");
        $keys = array_keys($form);
        sort($keys);

        return $keys;
    }
}
