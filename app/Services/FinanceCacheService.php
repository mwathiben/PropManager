<?php

namespace App\Services;

use App\Jobs\WarmFinanceCacheJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FinanceCacheService
{
    private const CACHE_PREFIX = 'finance';

    private const STATS_TTL = 300;

    private const REPORTS_TTL = 600;

    public static function statsKey(string $type, int $landlordId, ?string $suffix = null): string
    {
        $key = self::CACHE_PREFIX.":{$type}:{$landlordId}";

        return $suffix ? "{$key}:{$suffix}" : $key;
    }

    public static function reportKey(string $type, int $landlordId, array $filters = []): string
    {
        $filtersHash = md5(json_encode($filters));

        return self::CACHE_PREFIX.":report:{$type}:{$landlordId}:{$filtersHash}";
    }

    public static function reportRegistryKey(int $landlordId): string
    {
        return self::CACHE_PREFIX.":report_keys:{$landlordId}";
    }

    public static function getStatsTtl(): int
    {
        return self::STATS_TTL;
    }

    public static function getReportsTtl(): int
    {
        return self::REPORTS_TTL;
    }

    public static function invalidateForLandlord(int $landlordId): void
    {
        self::invalidateStats($landlordId);
        self::invalidateReports($landlordId);

        Log::channel('cache')->info('Cache invalidated', [
            'landlord_id' => $landlordId,
            'scope' => 'all',
        ]);
    }

    public static function invalidateAndWarm(int $landlordId): void
    {
        self::invalidateForLandlord($landlordId);
        WarmFinanceCacheJob::dispatch($landlordId)->delay(2);
    }

    public static function invalidateStats(int $landlordId): void
    {
        $keys = [
            self::statsKey('hub', $landlordId),
            self::statsKey('overview', $landlordId, now()->format('Y-m')),
            self::statsKey('trend', $landlordId),
            self::statsKey('arrears', $landlordId),
            self::statsKey('deposits', $landlordId),
            self::statsKey('latefees', $landlordId),
            self::statsKey('expenses', $landlordId),
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    public static function invalidateReports(int $landlordId): void
    {
        $registryKey = self::reportRegistryKey($landlordId);
        $keys = Cache::get($registryKey, []);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Cache::forget($registryKey);
    }

    public static function rememberStats(string $type, int $landlordId, callable $callback, ?string $suffix = null): mixed
    {
        $key = self::statsKey($type, $landlordId, $suffix);
        $hit = true;

        $result = Cache::remember($key, self::STATS_TTL, function () use ($callback, &$hit) {
            $hit = false;

            return $callback();
        });

        Log::channel('cache')->debug($hit ? 'Cache hit' : 'Cache miss', [
            'key' => $key,
            'type' => 'stats',
        ]);

        return $result;
    }

    public static function rememberReport(string $type, int $landlordId, array $filters, callable $callback): mixed
    {
        $key = self::reportKey($type, $landlordId, $filters);
        $hit = true;

        $result = Cache::remember($key, self::REPORTS_TTL, function () use ($callback, &$hit) {
            $hit = false;

            return $callback();
        });

        Log::channel('cache')->debug($hit ? 'Cache hit' : 'Cache miss', [
            'key' => $key,
            'type' => 'report',
        ]);

        self::registerReportKey($landlordId, $key);

        return $result;
    }

    private static function registerReportKey(int $landlordId, string $key): void
    {
        $registryKey = self::reportRegistryKey($landlordId);
        $keys = Cache::get($registryKey, []);

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::put($registryKey, $keys, self::REPORTS_TTL + 60);
        }
    }

    public static function superAdminKey(string $type): string
    {
        return self::CACHE_PREFIX.":superadmin:{$type}";
    }

    public static function rememberSuperAdminStats(string $type, callable $callback): mixed
    {
        $key = self::superAdminKey($type);

        return Cache::remember($key, self::STATS_TTL, $callback);
    }

    public static function invalidateSuperAdminStats(): void
    {
        Cache::forget(self::superAdminKey('metrics'));
    }
}
