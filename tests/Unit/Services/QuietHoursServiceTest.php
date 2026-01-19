<?php

namespace Tests\Unit\Services;

use App\Models\Notification;
use App\Services\QuietHoursService;
use App\ValueObjects\QuietHoursConfig;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class QuietHoursServiceTest extends TestCase
{
    private QuietHoursService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QuietHoursService;
    }

    public function test_quiet_hours_disabled_returns_false(): void
    {
        $config = new QuietHoursConfig(
            enabled: false,
            start: '22:00',
            end: '08:00',
            timezone: 'Africa/Nairobi'
        );

        $now = Carbon::parse('2024-01-15 23:30:00', 'Africa/Nairobi');

        $this->assertFalse($this->service->isQuietHours($config, $now));
    }

    public function test_quiet_hours_during_quiet_period_returns_true(): void
    {
        $config = new QuietHoursConfig(
            enabled: true,
            start: '22:00',
            end: '08:00',
            timezone: 'Africa/Nairobi'
        );

        $now = Carbon::parse('2024-01-15 23:30:00', 'Africa/Nairobi');

        $this->assertTrue($this->service->isQuietHours($config, $now));
    }

    public function test_quiet_hours_outside_quiet_period_returns_false(): void
    {
        $config = new QuietHoursConfig(
            enabled: true,
            start: '22:00',
            end: '08:00',
            timezone: 'Africa/Nairobi'
        );

        $now = Carbon::parse('2024-01-15 12:00:00', 'Africa/Nairobi');

        $this->assertFalse($this->service->isQuietHours($config, $now));
    }

    public function test_overnight_quiet_hours_before_midnight(): void
    {
        $config = new QuietHoursConfig(
            enabled: true,
            start: '22:00',
            end: '08:00',
            timezone: 'Africa/Nairobi'
        );

        $now = Carbon::parse('2024-01-15 22:00:00', 'Africa/Nairobi');

        $this->assertTrue($this->service->isQuietHours($config, $now));
    }

    public function test_overnight_quiet_hours_after_midnight(): void
    {
        $config = new QuietHoursConfig(
            enabled: true,
            start: '22:00',
            end: '08:00',
            timezone: 'Africa/Nairobi'
        );

        $now = Carbon::parse('2024-01-15 03:00:00', 'Africa/Nairobi');

        $this->assertTrue($this->service->isQuietHours($config, $now));
    }

    public function test_overnight_quiet_hours_at_end_boundary(): void
    {
        $config = new QuietHoursConfig(
            enabled: true,
            start: '22:00',
            end: '08:00',
            timezone: 'Africa/Nairobi'
        );

        $now = Carbon::parse('2024-01-15 08:00:00', 'Africa/Nairobi');

        $this->assertFalse($this->service->isQuietHours($config, $now));
    }

    public function test_daytime_quiet_hours(): void
    {
        $config = new QuietHoursConfig(
            enabled: true,
            start: '12:00',
            end: '14:00',
            timezone: 'Africa/Nairobi'
        );

        $now = Carbon::parse('2024-01-15 13:00:00', 'Africa/Nairobi');

        $this->assertTrue($this->service->isQuietHours($config, $now));
    }

    public function test_critical_urgency_bypasses_quiet_hours(): void
    {
        $this->assertTrue($this->service->canBypassQuietHours(Notification::URGENCY_CRITICAL));
    }

    public function test_urgent_urgency_bypasses_quiet_hours(): void
    {
        $this->assertTrue($this->service->canBypassQuietHours(Notification::URGENCY_URGENT));
    }

    public function test_important_urgency_respects_quiet_hours(): void
    {
        $this->assertFalse($this->service->canBypassQuietHours(Notification::URGENCY_IMPORTANT));
    }

    public function test_informational_urgency_respects_quiet_hours(): void
    {
        $this->assertFalse($this->service->canBypassQuietHours(Notification::URGENCY_INFORMATIONAL));
    }

    public function test_should_defer_returns_false_for_critical(): void
    {
        $config = new QuietHoursConfig(
            enabled: true,
            start: '22:00',
            end: '08:00',
            timezone: 'Africa/Nairobi'
        );

        Carbon::setTestNow(Carbon::parse('2024-01-15 23:30:00', 'Africa/Nairobi'));

        $this->assertFalse($this->service->shouldDefer($config, Notification::URGENCY_CRITICAL));

        Carbon::setTestNow();
    }

    public function test_should_defer_returns_true_for_important_during_quiet_hours(): void
    {
        $config = new QuietHoursConfig(
            enabled: true,
            start: '22:00',
            end: '08:00',
            timezone: 'Africa/Nairobi'
        );

        Carbon::setTestNow(Carbon::parse('2024-01-15 23:30:00', 'Africa/Nairobi'));

        $this->assertTrue($this->service->shouldDefer($config, Notification::URGENCY_IMPORTANT));

        Carbon::setTestNow();
    }

    public function test_should_defer_returns_false_outside_quiet_hours(): void
    {
        $config = new QuietHoursConfig(
            enabled: true,
            start: '22:00',
            end: '08:00',
            timezone: 'Africa/Nairobi'
        );

        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00', 'Africa/Nairobi'));

        $this->assertFalse($this->service->shouldDefer($config, Notification::URGENCY_IMPORTANT));

        Carbon::setTestNow();
    }

    public function test_next_delivery_time_today_end(): void
    {
        $config = new QuietHoursConfig(
            enabled: true,
            start: '22:00',
            end: '08:00',
            timezone: 'Africa/Nairobi'
        );

        Carbon::setTestNow(Carbon::parse('2024-01-15 03:00:00', 'Africa/Nairobi'));

        $nextDelivery = $this->service->getNextDeliveryTime($config);

        $this->assertEquals('2024-01-15 08:00:00', $nextDelivery->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_next_delivery_time_tomorrow_end(): void
    {
        $config = new QuietHoursConfig(
            enabled: true,
            start: '22:00',
            end: '08:00',
            timezone: 'Africa/Nairobi'
        );

        Carbon::setTestNow(Carbon::parse('2024-01-15 23:00:00', 'Africa/Nairobi'));

        $nextDelivery = $this->service->getNextDeliveryTime($config);

        $this->assertEquals('2024-01-16 08:00:00', $nextDelivery->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_timezone_respects_user_timezone_utc(): void
    {
        $config = new QuietHoursConfig(
            enabled: true,
            start: '22:00',
            end: '08:00',
            timezone: 'UTC'
        );

        $now = Carbon::parse('2024-01-15 23:00:00', 'UTC');

        $this->assertTrue($this->service->isQuietHours($config, $now));
    }

    public function test_timezone_respects_user_timezone_different_zone(): void
    {
        $configNairobi = new QuietHoursConfig(
            enabled: true,
            start: '22:00',
            end: '08:00',
            timezone: 'Africa/Nairobi'
        );

        $configLondon = new QuietHoursConfig(
            enabled: true,
            start: '22:00',
            end: '08:00',
            timezone: 'Europe/London'
        );

        $now = Carbon::parse('2024-01-15 20:00:00', 'Europe/London');
        $nairobiTime = $now->copy()->setTimezone('Africa/Nairobi');

        $this->assertFalse($this->service->isQuietHours($configLondon, $now));
        $this->assertTrue($this->service->isQuietHours($configNairobi, $nairobiTime));
    }

    public function test_disabled_config_factory(): void
    {
        $config = QuietHoursConfig::disabled();

        $this->assertFalse($config->enabled);
        $this->assertEquals('22:00', $config->start);
        $this->assertEquals('08:00', $config->end);
    }
}
