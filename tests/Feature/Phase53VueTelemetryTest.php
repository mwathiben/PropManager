<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MetricsService;
use Mockery;
use Tests\TestCase;

/**
 * Phase-53 VUE-TELEMETRY-1/2/3 watchdog. Verifies the
 * /api/telemetry/vue-preview-poll-pause endpoint accepts sendBeacon
 * payloads, increments the gauge, and validates input.
 */
class Phase53VueTelemetryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_accepts_valid_payload_and_increments_gauge(): void
    {
        $captured = null;
        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('increment')
            ->once()
            ->withArgs(function (string $name, int $by, array $labels) use (&$captured) {
                $captured = ['name' => $name, 'by' => $by, 'labels' => $labels];

                return $name === 'vue_preview_poll_pause_count';
            })
            ->andReturnNull();
        $this->app->instance(MetricsService::class, $metrics);

        $this->postJson(route('telemetry.vue-preview-poll-pause'), [
            'count' => 5,
            'route' => 'reports.scheduled',
        ])->assertNoContent();

        $this->assertSame('vue_preview_poll_pause_count', $captured['name']);
        $this->assertSame(5, $captured['by']);
        $this->assertSame(['route' => 'reports.scheduled'], $captured['labels']);
    }

    public function test_rejects_missing_count(): void
    {
        $this->postJson(route('telemetry.vue-preview-poll-pause'), [
            'route' => 'reports.scheduled',
        ])->assertUnprocessable();
    }

    public function test_rejects_negative_count(): void
    {
        $this->postJson(route('telemetry.vue-preview-poll-pause'), [
            'count' => -1,
            'route' => 'reports.scheduled',
        ])->assertUnprocessable();
    }

    public function test_rejects_count_above_ceiling(): void
    {
        $this->postJson(route('telemetry.vue-preview-poll-pause'), [
            'count' => 5000,
            'route' => 'reports.scheduled',
        ])->assertUnprocessable();
    }

    public function test_sanitises_route_label_for_prometheus(): void
    {
        $captured = null;
        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('increment')
            ->withArgs(function (string $name, int $by, array $labels) use (&$captured) {
                $captured = $labels;

                return true;
            })
            ->andReturnNull();
        $this->app->instance(MetricsService::class, $metrics);

        $this->postJson(route('telemetry.vue-preview-poll-pause'), [
            'count' => 1,
            'route' => 'reports.scheduled space "quote" 🎉',
        ])->assertNoContent();

        $this->assertArrayHasKey('route', $captured);
        $this->assertDoesNotMatchRegularExpression('/[^a-z0-9._-]/', $captured['route']);
    }

    public function test_route_is_not_csrf_protected(): void
    {
        // sendBeacon does not send CSRF tokens — assert this endpoint
        // doesn't require them. We simulate by posting without the
        // X-CSRF-TOKEN header that browsers would set for fetch().
        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('increment')->andReturnNull();
        $this->app->instance(MetricsService::class, $metrics);

        $this->post(route('telemetry.vue-preview-poll-pause'), [
            'count' => 1,
            'route' => 'reports.scheduled',
        ])->assertNoContent();
    }
}
