<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\WaterConnection;
use App\Services\Water\WaterAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-96 WATER-CLIENT-DASHBOARD: surface guards — the shared components the
 * dashboard composes exist, the service contract is stable, and i18n is in parity.
 */
class Phase96WaterClientDashboardSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_water_components_are_present(): void
    {
        $base = resource_path('js/Components/Water');
        foreach ([
            'WaterDisconnectionBanner.vue',
            'WaterUsageAlert.vue',
            'WaterConsumptionCard.vue',
            'WaterChargesCard.vue',
        ] as $component) {
            $this->assertFileExists($base.DIRECTORY_SEPARATOR.$component);
        }

        $this->assertFileExists(resource_path('js/Pages/WaterClient/Dashboard.vue'));
    }

    public function test_overview_for_connection_returns_the_shared_shape(): void
    {
        $connection = new WaterConnection(['meter_id' => null, 'billing_mode' => 'flat_rate']);

        $overview = app(WaterAccountService::class)->overviewForConnection($connection);

        $this->assertSame(
            ['history', 'summary', 'alert', 'charges', 'disconnection'],
            array_keys($overview),
        );
        $this->assertSame(
            ['latest_consumption', 'latest_date', 'avg_monthly', 'ytd_consumption'],
            array_keys($overview['summary']),
        );
        $this->assertArrayHasKey('disconnected', $overview['disconnection']);
    }

    public function test_client_dash_lang_parity(): void
    {
        $en = require lang_path('en/water.php');
        $sw = require lang_path('sw/water.php');
        $ar = require lang_path('ar/water.php');

        $keys = array_keys($en['client_dash']);
        sort($keys);

        foreach (['rate_label', 'per_unit', 'rate_not_set', 'flat_rate_note', 'metering_pending'] as $required) {
            $this->assertContains($required, $keys, "en missing client_dash.$required");
        }

        $swKeys = array_keys($sw['client_dash']);
        $arKeys = array_keys($ar['client_dash']);
        sort($swKeys);
        sort($arKeys);

        $this->assertSame($keys, $swKeys, 'sw client_dash keys diverge from en');
        $this->assertSame($keys, $arKeys, 'ar client_dash keys diverge from en');
    }
}
