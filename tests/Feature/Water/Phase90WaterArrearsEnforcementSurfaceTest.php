<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-90 WATER-ARREARS-ENFORCEMENT surface guard: schema, notification type,
 * routes, command, i18n parity.
 */
class Phase90WaterArrearsEnforcementSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_exists(): void
    {
        $this->assertTrue(Schema::hasColumns('water_meters', ['disconnected_at', 'disconnect_reason']));
        $this->assertTrue(Schema::hasColumn('payment_configurations', 'water_reconnection_fee'));
        $this->assertTrue(Schema::hasColumn('buildings', 'water_reconnection_fee'));
        $this->assertTrue(Schema::hasTable('water_pending_charges'));
        $this->assertTrue(Schema::hasColumn('notification_preferences', 'water_arrears_enabled'));
    }

    public function test_water_arrears_type_mapped(): void
    {
        $this->assertArrayHasKey(Notification::TYPE_WATER_ARREARS, Notification::TYPE_URGENCY_MAP);
        $this->assertSame(Notification::URGENCY_IMPORTANT, Notification::TYPE_URGENCY_MAP[Notification::TYPE_WATER_ARREARS]);
    }

    public function test_routes_and_command(): void
    {
        $this->assertTrue(Route::has('meters.disconnect'));
        $this->assertTrue(Route::has('meters.reconnect'));
        $this->assertSame(0, Artisan::call('water:arrears-notify', ['--dry-run' => true]));
    }

    public function test_lang_parity(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $meter = require base_path("lang/{$locale}/meter.php");
            $water = require base_path("lang/{$locale}/water.php");
            // 2026-05-28: water settings form-body keyspace moved from
            // water.settings.* to water_settings_form.* — reader rerouted.
            $form = require base_path("lang/{$locale}/water_settings_form.php");
            $this->assertArrayHasKey('disconnect', $meter, "{$locale} missing meter.disconnect");
            $this->assertArrayHasKey('arrears', $meter, "{$locale} missing meter.arrears");
            $this->assertArrayHasKey('reconnection_fee', $form, "{$locale} missing water_settings_form.reconnection_fee");
            $this->assertArrayHasKey('arrears_subject', $water['notify'] ?? [], "{$locale} missing water.notify.arrears_subject");
            $this->assertArrayHasKey('disconnected_title', $water['tenant'] ?? [], "{$locale} missing water.tenant.disconnected_title");
        }
    }
}
