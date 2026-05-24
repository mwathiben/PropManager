<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-97 WATER-CLIENT-BILLING: surface guards — schema, routes, command,
 * notification plumbing, the finances page, and i18n parity.
 */
class Phase97WaterClientBillingSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_water_client_charges_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('water_client_charges'));
        foreach (['landlord_id', 'water_connection_id', 'billing_period_start', 'consumption', 'water_due', 'amount_paid', 'status', 'due_date', 'deleted_at'] as $col) {
            $this->assertTrue(Schema::hasColumn('water_client_charges', $col), "missing column $col");
        }
    }

    public function test_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('water-client.finances'));
        $this->assertTrue(Route::has('water.connections.record-payment'));
    }

    public function test_bill_command_is_registered(): void
    {
        $this->assertArrayHasKey('water:bill-clients', \Illuminate\Support\Facades\Artisan::all());
    }

    public function test_notification_plumbing_is_present(): void
    {
        $this->assertSame('water_bill_due', Notification::TYPE_WATER_BILL_DUE);
        $this->assertArrayHasKey(Notification::TYPE_WATER_BILL_DUE, Notification::TYPE_URGENCY_MAP);
        $this->assertTrue(Schema::hasColumn('notification_preferences', 'water_bill_due_enabled'));
    }

    public function test_finances_page_present(): void
    {
        $this->assertFileExists(resource_path('js/Pages/WaterClient/Finances.vue'));
    }

    public function test_lang_parity_for_billing_keys(): void
    {
        $en = require lang_path('en/water.php');
        $sw = require lang_path('sw/water.php');
        $ar = require lang_path('ar/water.php');

        foreach (['client_finances'] as $group) {
            $keys = array_keys($en[$group]);
            sort($keys);
            $swKeys = array_keys($sw[$group]);
            $arKeys = array_keys($ar[$group]);
            sort($swKeys);
            sort($arKeys);
            $this->assertSame($keys, $swKeys, "sw $group keys diverge");
            $this->assertSame($keys, $arKeys, "ar $group keys diverge");
        }

        foreach (['outstanding', 'record_payment', 'payment_amount', 'payment_recorded'] as $key) {
            $this->assertArrayHasKey($key, $en['clients']);
            $this->assertArrayHasKey($key, $sw['clients']);
            $this->assertArrayHasKey($key, $ar['clients']);
        }
    }
}
