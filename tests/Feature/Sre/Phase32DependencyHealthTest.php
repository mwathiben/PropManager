<?php

declare(strict_types=1);

namespace Tests\Feature\Sre;

use App\Events\DegradationDetected;
use App\Models\AlertFiring;
use App\Services\Sre\DependencyHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class Phase32DependencyHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_returns_uniform_shape(): void
    {
        // smtp uses gethostbyname which works without network; localhost
        // resolves to 127.0.0.1 so status is up.
        config(['mail.mailers.smtp.host' => 'localhost']);
        $result = app(DependencyHealthService::class)->check('smtp');

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('latency_ms', $result);
        $this->assertArrayHasKey('checked_at', $result);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_check_caches_within_60s_window(): void
    {
        config(['mail.mailers.smtp.host' => 'localhost']);
        Cache::flush();
        $svc = app(DependencyHealthService::class);

        $first = $svc->check('smtp');
        $second = $svc->check('smtp');

        $this->assertSame($first['checked_at'], $second['checked_at']);
    }

    public function test_unknown_dep_returns_down(): void
    {
        Cache::flush();
        $result = app(DependencyHealthService::class)->check('not-a-real-dep');
        $this->assertSame(DependencyHealthService::STATUS_DOWN, $result['status']);
    }

    public function test_outbound_health_check_runs_and_emits_per_dep_line(): void
    {
        Cache::flush();
        config(['mail.mailers.smtp.host' => 'localhost']);

        $mock = Mockery::mock(DependencyHealthService::class);
        $mock->shouldReceive('check')->andReturn([
            'status' => 'up',
            'latency_ms' => 42,
            'checked_at' => now()->toIso8601String(),
            'error' => null,
        ]);
        $this->app->instance(DependencyHealthService::class, $mock);

        $exit = \Artisan::call('outbound:health-check --dep=smtp');
        $output = \Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('smtp', $output);
        $this->assertStringContainsString('status=up', $output);
    }

    public function test_down_dep_records_alert_firing(): void
    {
        Cache::flush();
        $mock = Mockery::mock(DependencyHealthService::class);
        $mock->shouldReceive('check')->andReturn([
            'status' => 'down',
            'latency_ms' => 5000,
            'checked_at' => now()->toIso8601String(),
            'error' => 'timeout',
        ]);
        $this->app->instance(DependencyHealthService::class, $mock);

        \Artisan::call('outbound:health-check --dep=daraja');

        $this->assertDatabaseHas('alert_firings', [
            'alert_key' => 'dependency_down',
            'severity' => 'sev2',
        ]);
    }

    public function test_state_transition_fires_degradation_event(): void
    {
        Event::fake([DegradationDetected::class]);
        Cache::flush();
        Cache::put('sre:dep-prev-status:daraja', 'up', now()->addHours(24));

        $mock = Mockery::mock(DependencyHealthService::class);
        $mock->shouldReceive('check')->andReturn([
            'status' => 'down',
            'latency_ms' => 5000,
            'checked_at' => now()->toIso8601String(),
            'error' => 'timeout',
        ]);
        $this->app->instance(DependencyHealthService::class, $mock);

        \Artisan::call('outbound:health-check --dep=daraja');

        Event::assertDispatched(DegradationDetected::class, fn ($e) => $e->dependency === 'daraja' && $e->previousStatus === 'up' && $e->currentStatus === 'down');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
