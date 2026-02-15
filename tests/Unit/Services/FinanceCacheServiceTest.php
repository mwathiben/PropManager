<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\FinanceCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceCacheServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function stats_key_format_includes_prefix_type_and_landlord(): void
    {
        $key = FinanceCacheService::statsKey('hub', 42);

        $this->assertSame('finance:hub:42', $key);
    }

    #[Test]
    public function stats_key_format_with_suffix(): void
    {
        $key = FinanceCacheService::statsKey('overview', 5, '2026-02');

        $this->assertSame('finance:overview:5:2026-02', $key);
    }

    #[Test]
    public function report_key_format_includes_filters_hash(): void
    {
        $filters = ['building_id' => 1, 'month' => '2026-01'];
        $key = FinanceCacheService::reportKey('occupancy', 10, $filters);

        $expectedHash = md5(json_encode($filters));
        $this->assertSame("finance:report:occupancy:10:{$expectedHash}", $key);
    }

    #[Test]
    public function report_registry_key_format(): void
    {
        $key = FinanceCacheService::reportRegistryKey(42);

        $this->assertSame('finance:report_keys:42', $key);
    }

    #[Test]
    public function invalidate_reports_clears_all_report_keys_on_database_driver(): void
    {
        $landlordId = 1;

        FinanceCacheService::rememberReport('occupancy', $landlordId, ['a' => 1], fn () => ['data1']);
        FinanceCacheService::rememberReport('arrears', $landlordId, ['b' => 2], fn () => ['data2']);
        FinanceCacheService::rememberReport('revenue', $landlordId, ['c' => 3], fn () => ['data3']);

        $key1 = FinanceCacheService::reportKey('occupancy', $landlordId, ['a' => 1]);
        $key2 = FinanceCacheService::reportKey('arrears', $landlordId, ['b' => 2]);
        $key3 = FinanceCacheService::reportKey('revenue', $landlordId, ['c' => 3]);

        $this->assertTrue(Cache::has($key1));
        $this->assertTrue(Cache::has($key2));
        $this->assertTrue(Cache::has($key3));

        FinanceCacheService::invalidateReports($landlordId);

        $this->assertFalse(Cache::has($key1));
        $this->assertFalse(Cache::has($key2));
        $this->assertFalse(Cache::has($key3));
    }

    #[Test]
    public function invalidate_reports_does_not_affect_other_landlords(): void
    {
        FinanceCacheService::rememberReport('occupancy', 1, ['x' => 1], fn () => ['landlord1']);
        FinanceCacheService::rememberReport('occupancy', 2, ['x' => 1], fn () => ['landlord2']);

        $key1 = FinanceCacheService::reportKey('occupancy', 1, ['x' => 1]);
        $key2 = FinanceCacheService::reportKey('occupancy', 2, ['x' => 1]);

        FinanceCacheService::invalidateReports(1);

        $this->assertFalse(Cache::has($key1));
        $this->assertTrue(Cache::has($key2));
    }

    #[Test]
    public function invalidate_reports_cleans_up_registry(): void
    {
        $landlordId = 5;

        FinanceCacheService::rememberReport('revenue', $landlordId, [], fn () => ['data']);

        $registryKey = FinanceCacheService::reportRegistryKey($landlordId);
        $this->assertNotNull(Cache::get($registryKey));

        FinanceCacheService::invalidateReports($landlordId);

        $this->assertNull(Cache::get($registryKey));
    }

    #[Test]
    public function invalidate_reports_handles_empty_registry(): void
    {
        FinanceCacheService::invalidateReports(999);

        $this->assertNull(Cache::get(FinanceCacheService::reportRegistryKey(999)));
    }

    #[Test]
    public function remember_report_does_not_duplicate_registry_entries(): void
    {
        $landlordId = 3;
        $filters = ['month' => '2026-01'];

        FinanceCacheService::rememberReport('occupancy', $landlordId, $filters, fn () => ['first']);
        FinanceCacheService::rememberReport('occupancy', $landlordId, $filters, fn () => ['second']);

        $registryKey = FinanceCacheService::reportRegistryKey($landlordId);
        $registry = Cache::get($registryKey, []);

        $uniqueKeys = array_unique($registry);
        $this->assertCount(count($uniqueKeys), $registry);
    }

    #[Test]
    public function remember_stats_logs_cache_miss_on_first_call(): void
    {
        Log::shouldReceive('channel')->with('cache')->andReturnSelf();
        Log::shouldReceive('debug')->once()->withArgs(function (string $message, array $context) {
            return $message === 'Cache miss'
                && $context['key'] === 'finance:hub:10'
                && $context['type'] === 'stats';
        });

        FinanceCacheService::rememberStats('hub', 10, fn () => ['data']);
    }

    #[Test]
    public function remember_stats_logs_cache_hit_on_second_call(): void
    {
        Cache::put(FinanceCacheService::statsKey('hub', 10), ['cached'], 300);

        Log::shouldReceive('channel')->with('cache')->andReturnSelf();
        Log::shouldReceive('debug')->once()->withArgs(function (string $message, array $context) {
            return $message === 'Cache hit'
                && $context['key'] === 'finance:hub:10'
                && $context['type'] === 'stats';
        });

        FinanceCacheService::rememberStats('hub', 10, fn () => ['fresh']);
    }

    #[Test]
    public function invalidate_for_landlord_logs_invalidation(): void
    {
        Log::shouldReceive('channel')->with('cache')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(function (string $message, array $context) {
            return $message === 'Cache invalidated'
                && $context['landlord_id'] === 42
                && $context['scope'] === 'all';
        });

        FinanceCacheService::invalidateForLandlord(42);
    }
}
