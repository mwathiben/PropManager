<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class FinanceCacheService
{
    private const CACHE_PREFIX = 'finance';
    private const STATS_TTL = 300; // 5 minutes
    private const REPORTS_TTL = 600; // 10 minutes

    public static function statsKey(string $type, int $landlordId, ?string $suffix = null): string
    {
        $key = self::CACHE_PREFIX . ":{$type}:{$landlordId}";

        return $suffix ? "{$key}:{$suffix}" : $key;
    }

    public static function reportKey(string $type, int $landlordId, array $filters = []): string
    {
        $filtersHash = md5(json_encode($filters));

        return self::CACHE_PREFIX . ":report:{$type}:{$landlordId}:{$filtersHash}";
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
        $pattern = self::CACHE_PREFIX . ":report:*:{$landlordId}:*";
        self::deleteByPattern($pattern);
    }

    private static function deleteByPattern(string $pattern): void
    {
        $redis = Cache::store('redis')->getRedis();

        if (method_exists($redis, 'keys')) {
            $prefix = config('cache.prefix', '');
            $keys = $redis->keys($prefix . $pattern);

            foreach ($keys as $key) {
                $keyWithoutPrefix = str_replace($prefix, '', $key);
                Cache::forget($keyWithoutPrefix);
            }
        }
    }

    public static function rememberStats(string $type, int $landlordId, callable $callback, ?string $suffix = null): mixed
    {
        $key = self::statsKey($type, $landlordId, $suffix);

        return Cache::remember($key, self::STATS_TTL, $callback);
    }

    public static function rememberReport(string $type, int $landlordId, array $filters, callable $callback): mixed
    {
        $key = self::reportKey($type, $landlordId, $filters);

        return Cache::remember($key, self::REPORTS_TTL, $callback);
    }
}
