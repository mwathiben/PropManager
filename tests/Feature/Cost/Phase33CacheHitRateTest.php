<?php

declare(strict_types=1);

namespace Tests\Feature\Cost;

use App\Models\AlertFiring;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class Phase33CacheHitRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_emits_gauge_for_each_bucket_with_traffic(): void
    {
        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('snapshot')->andReturn([
            'cache_hit_total{cache=finance,type=stats}' => 80,
            'cache_miss_total{cache=finance,type=stats}' => 20,
            'cache_hit_total{cache=building,type=list}' => 5,
            'cache_miss_total{cache=building,type=list}' => 95,
        ]);
        $emitted = [];
        $metrics->shouldReceive('gauge')->andReturnUsing(function ($name, $value, $labels) use (&$emitted) {
            $emitted[$name][] = ['value' => $value, 'labels' => $labels];
        });
        $this->app->instance(MetricsService::class, $metrics);

        $exit = \Artisan::call('cache:hit-rate-audit');
        $output = \Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('ratio=0.800', $output);
        $this->assertStringContainsString('ratio=0.050', $output);
        $this->assertGreaterThanOrEqual(2, count($emitted['cache_hit_rate_ratio'] ?? []));
    }

    public function test_audit_fires_alert_when_bucket_drops_below_threshold(): void
    {
        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('snapshot')->andReturn([
            'cache_hit_total{cache=finance,type=stats}' => 5,
            'cache_miss_total{cache=finance,type=stats}' => 95,
        ]);
        $metrics->shouldReceive('gauge')->byDefault();
        $this->app->instance(MetricsService::class, $metrics);

        \Artisan::call('cache:hit-rate-audit');

        $this->assertDatabaseHas('alert_firings', [
            'alert_key' => 'low_cache_hit_rate',
            'severity' => 'sev3',
        ]);
    }

    public function test_audit_resolves_alert_when_all_above_threshold(): void
    {
        AlertFiring::create([
            'alert_key' => 'low_cache_hit_rate',
            'severity' => 'sev3',
            'value' => 0.1,
            'threshold' => 0.5,
            'fired_at' => now()->subHour(),
        ]);

        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('snapshot')->andReturn([
            'cache_hit_total{cache=finance,type=stats}' => 95,
            'cache_miss_total{cache=finance,type=stats}' => 5,
        ]);
        $metrics->shouldReceive('gauge')->byDefault();
        $this->app->instance(MetricsService::class, $metrics);

        \Artisan::call('cache:hit-rate-audit');

        $firing = AlertFiring::query()->where('alert_key', 'low_cache_hit_rate')->latest('id')->first();
        $this->assertNotNull($firing->resolved_at);
    }

    public function test_audit_skips_buckets_with_zero_traffic(): void
    {
        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('snapshot')->andReturn([
            'cache_hit_total{cache=finance,type=stats}' => 0,
            'cache_miss_total{cache=finance,type=stats}' => 0,
        ]);
        $metrics->shouldReceive('gauge')->byDefault();
        $this->app->instance(MetricsService::class, $metrics);

        $exit = \Artisan::call('cache:hit-rate-audit');
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Audited 0 cache bucket(s)', $output);
    }

    public function test_audit_ignores_non_cache_fields(): void
    {
        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('snapshot')->andReturn([
            'http_request_count' => 1000,
            'queue_depth' => 50,
        ]);
        $metrics->shouldReceive('gauge')->byDefault();
        $this->app->instance(MetricsService::class, $metrics);

        $exit = \Artisan::call('cache:hit-rate-audit');
        $this->assertSame(0, $exit);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
