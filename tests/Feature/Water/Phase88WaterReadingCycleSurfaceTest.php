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
 * Phase-88 WATER-READING-CYCLE surface guard: schema, notification types,
 * commands, route, and i18n parity.
 */
class Phase88WaterReadingCycleSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_config_schema_exists(): void
    {
        $this->assertTrue(Schema::hasColumns('payment_configurations', ['water_reading_day', 'water_review_days']));
        $this->assertTrue(Schema::hasColumns('buildings', ['water_reading_day', 'water_review_days']));
        $this->assertTrue(Schema::hasColumn('water_readings', 'auto_approved'));
        $this->assertTrue(Schema::hasColumns('notification_preferences', ['water_reading_due_enabled', 'water_review_due_enabled']));
    }

    public function test_notification_types_are_mapped(): void
    {
        $this->assertArrayHasKey(Notification::TYPE_WATER_READING_DUE, Notification::TYPE_URGENCY_MAP);
        $this->assertArrayHasKey(Notification::TYPE_WATER_REVIEW_DUE, Notification::TYPE_URGENCY_MAP);
        // IMPORTANT so they reach email + in-app by default.
        $this->assertSame(Notification::URGENCY_IMPORTANT, Notification::TYPE_URGENCY_MAP[Notification::TYPE_WATER_READING_DUE]);
        $this->assertSame(Notification::URGENCY_IMPORTANT, Notification::TYPE_URGENCY_MAP[Notification::TYPE_WATER_REVIEW_DUE]);
    }

    public function test_cycle_commands_exit_zero(): void
    {
        $this->assertSame(0, Artisan::call('water:reading-reminders', ['--dry-run' => true]));
        $this->assertSame(0, Artisan::call('water:review-window', ['--dry-run' => true]));
    }

    public function test_reread_route_is_registered(): void
    {
        $this->assertTrue(Route::has('readings.request-reread'));
    }

    public function test_water_notify_lang_parity(): void
    {
        $en = $this->notifyKeys('en');
        $sw = $this->notifyKeys('sw');
        $ar = $this->notifyKeys('ar');

        $this->assertSame($en, $sw, 'sw water.notify keys diverge from en');
        $this->assertSame($en, $ar, 'ar water.notify keys diverge from en');
        $this->assertContains('reading_due_subject', $en);
        $this->assertContains('auto_approved_body', $en);
    }

    /**
     * @return list<string>
     */
    private function notifyKeys(string $locale): array
    {
        $water = require base_path("lang/{$locale}/water.php");
        $keys = array_keys($water['notify'] ?? []);
        sort($keys);

        return $keys;
    }
}
