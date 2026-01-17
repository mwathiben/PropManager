<?php

namespace App\Services;

use App\Models\Building;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class BuildingCacheService
{
    private const CACHE_PREFIX = 'building';

    private const BUILDING_TTL = 3600; // 1 hour

    public static function configKey(int $landlordId, int $buildingId): string
    {
        return self::CACHE_PREFIX.":config:{$landlordId}:{$buildingId}";
    }

    public static function listKey(int $landlordId): string
    {
        return self::CACHE_PREFIX.":list:{$landlordId}";
    }

    public static function detailKey(int $landlordId, int $buildingId): string
    {
        return self::CACHE_PREFIX.":detail:{$landlordId}:{$buildingId}";
    }

    public static function hierarchyKey(int $landlordId, int $buildingId): string
    {
        return self::CACHE_PREFIX.":hierarchy:{$landlordId}:{$buildingId}";
    }

    public static function getTtl(): int
    {
        return self::BUILDING_TTL;
    }

    public static function rememberConfig(int $landlordId, int $buildingId, callable $callback): mixed
    {
        $key = self::configKey($landlordId, $buildingId);

        return Cache::remember($key, self::BUILDING_TTL, $callback);
    }

    public static function rememberList(int $landlordId, callable $callback): mixed
    {
        $key = self::listKey($landlordId);

        return Cache::remember($key, self::BUILDING_TTL, $callback);
    }

    public static function rememberDetail(int $landlordId, int $buildingId, callable $callback): mixed
    {
        $key = self::detailKey($landlordId, $buildingId);

        return Cache::remember($key, self::BUILDING_TTL, $callback);
    }

    public static function rememberHierarchy(int $landlordId, int $buildingId, callable $callback): mixed
    {
        $key = self::hierarchyKey($landlordId, $buildingId);

        return Cache::remember($key, self::BUILDING_TTL, $callback);
    }

    public static function invalidateBuilding(Building $building): void
    {
        $landlordId = $building->landlord_id;
        $buildingId = $building->id;

        $keys = [
            self::configKey($landlordId, $buildingId),
            self::detailKey($landlordId, $buildingId),
            self::hierarchyKey($landlordId, $buildingId),
            self::listKey($landlordId),
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        if ($building->parent_building_id) {
            self::invalidateBuildingById($landlordId, $building->parent_building_id);
        }
    }

    public static function invalidateBuildingById(int $landlordId, int $buildingId): void
    {
        $keys = [
            self::configKey($landlordId, $buildingId),
            self::detailKey($landlordId, $buildingId),
            self::hierarchyKey($landlordId, $buildingId),
            self::listKey($landlordId),
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    public static function invalidateLandlordBuildings(int $landlordId): void
    {
        Cache::forget(self::listKey($landlordId));

        $pattern = self::CACHE_PREFIX.":*:{$landlordId}:*";
        self::deleteByPattern($pattern);
    }

    private static function deleteByPattern(string $pattern): void
    {
        if (config('cache.default') !== 'redis') {
            return;
        }

        try {
            $redis = Redis::connection('cache');

            if (method_exists($redis, 'keys')) {
                $prefix = config('cache.prefix', '');
                $keys = $redis->keys($prefix.$pattern);

                foreach ($keys as $key) {
                    $keyWithoutPrefix = str_replace($prefix, '', $key);
                    Cache::forget($keyWithoutPrefix);
                }
            }
        } catch (\Exception $e) {
            // Redis not available, skip pattern deletion
        }
    }

    public static function getBuildingWithConfig(int $landlordId, int $buildingId): ?Building
    {
        return self::rememberConfig($landlordId, $buildingId, function () use ($buildingId) {
            return Building::with(['property:id,name,address', 'caretaker:id,name,mobile_number'])
                ->find($buildingId);
        });
    }

    public static function getBuildingsForLandlord(int $landlordId): Collection
    {
        return self::rememberList($landlordId, function () use ($landlordId) {
            return Building::where('landlord_id', $landlordId)
                ->whereNull('parent_building_id')
                ->with(['property:id,name', 'wings:id,name,parent_building_id'])
                ->withCount('units')
                ->withCount(['units as occupied_units_count' => function ($q) {
                    $q->where('status', 'occupied');
                }])
                ->get();
        });
    }

    public static function getBuildingHierarchy(int $landlordId, int $buildingId): array
    {
        return self::rememberHierarchy($landlordId, $buildingId, function () use ($buildingId) {
            $building = Building::with([
                'wings' => fn ($q) => $q->withCount('units'),
            ])->withCount('units')->find($buildingId);

            if (! $building) {
                return ['building' => null, 'wings' => collect(), 'totalUnits' => 0];
            }

            $totalUnits = $building->units_count + $building->wings->sum('units_count');

            return [
                'building' => $building,
                'wings' => $building->wings,
                'totalUnits' => $totalUnits,
            ];
        });
    }
}
