<?php

declare(strict_types=1);

namespace Tests\Feature\Cost;

use App\Models\StorageTierPolicy;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class Phase33StorageTierTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_default_policies(): void
    {
        $this->seed(\Database\Seeders\Phase33StorageTierPolicySeeder::class);

        $this->assertDatabaseHas('storage_tier_policies', [
            'disk_name' => 's3',
            'path_prefix' => 'invoices/',
            'target_tier' => 'ia',
        ]);
        $this->assertSame(4, StorageTierPolicy::count());
    }

    public function test_tier_policy_audit_buckets_files_by_age(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('archive/recent.txt', 'A');
        Storage::disk('local')->put('archive/old.txt', 'B');

        // Backdate one file past the 30-day cutoff by setting mtime.
        touch(Storage::disk('local')->path('archive/old.txt'), strtotime('-90 days'));

        StorageTierPolicy::create([
            'disk_name' => 'local',
            'path_prefix' => 'archive/',
            'max_age_days' => 30,
            'target_tier' => StorageTierPolicy::TIER_GLACIER,
            'is_active' => true,
        ]);

        $emitted = [];
        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('gauge')->andReturnUsing(function ($name, $value, $labels) use (&$emitted) {
            $emitted[] = ['name' => $name, 'value' => $value, 'labels' => $labels];
        });
        $this->app->instance(MetricsService::class, $metrics);

        $exit = \Artisan::call('storage:tier-policy', ['--disk' => 'local']);
        $output = \Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Audited 1 policy', $output);

        $bytesGauges = array_values(array_filter($emitted, fn ($e) => $e['name'] === 'storage_bytes_by_tier_total'));
        $this->assertGreaterThanOrEqual(2, count($bytesGauges));
        $buckets = array_column(array_column($bytesGauges, 'labels'), 'bucket');
        $this->assertContains('current', $buckets);
        $this->assertContains('target', $buckets);
    }

    public function test_tier_policy_audit_warns_when_no_policies(): void
    {
        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('gauge')->byDefault();
        $this->app->instance(MetricsService::class, $metrics);

        $exit = \Artisan::call('storage:tier-policy');
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No active storage tier policies', $output);
    }

    public function test_cost_audit_converts_bytes_to_kes(): void
    {
        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('gaugeSnapshot')->andReturn([
            'storage_bytes_by_tier_total{bucket=current,disk=s3,prefix=invoices,target_tier=ia}' => (1024 ** 3) * 10,
            'storage_bytes_by_tier_total{bucket=target,disk=s3,prefix=invoices,target_tier=ia}' => (1024 ** 3) * 100,
            'storage_bytes_by_tier_total{bucket=target,disk=s3,prefix=exports,target_tier=glacier}' => (1024 ** 3) * 50,
        ]);
        $emitted = [];
        $metrics->shouldReceive('gauge')->andReturnUsing(function ($name, $value, $labels) use (&$emitted) {
            $emitted[$labels['tier'] ?? '_'] = $value;
        });
        $this->app->instance(MetricsService::class, $metrics);

        $exit = \Artisan::call('storage:cost-audit');
        $output = \Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Total projected monthly storage cost', $output);
        $this->assertEqualsWithDelta(10 * 3.34, $emitted['standard'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(100 * 1.81, $emitted['ia'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(50 * 0.58, $emitted['glacier'] ?? 0, 0.01);
    }

    public function test_cost_audit_handles_empty_snapshot(): void
    {
        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('gaugeSnapshot')->andReturn([]);
        $metrics->shouldReceive('gauge')->byDefault();
        $this->app->instance(MetricsService::class, $metrics);

        $exit = \Artisan::call('storage:cost-audit');
        $this->assertSame(0, $exit);
    }

    public function test_inactive_policies_are_skipped(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('archive/file.txt', 'A');

        StorageTierPolicy::create([
            'disk_name' => 'local',
            'path_prefix' => 'archive/',
            'max_age_days' => 30,
            'target_tier' => StorageTierPolicy::TIER_GLACIER,
            'is_active' => false,
        ]);

        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('gauge')->byDefault();
        $this->app->instance(MetricsService::class, $metrics);

        $exit = \Artisan::call('storage:tier-policy', ['--disk' => 'local']);
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No active storage tier policies', $output);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
