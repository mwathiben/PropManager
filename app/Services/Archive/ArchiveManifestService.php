<?php

declare(strict_types=1);

namespace App\Services\Archive;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ArchiveManifestService
{
    private const CACHE_TTL_SECONDS = 3600;

    public function availableMonthsForLandlord(int|string $landlordId): array
    {
        $key = sprintf('archive:manifest:landlord:%s', (string) $landlordId);

        return Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($landlordId) {
            $disk = Storage::disk('archive');
            $prefix = sprintf('product-events/%s/', (string) $landlordId);
            $files = $disk->allFiles($prefix);
            $months = [];
            foreach ($files as $file) {
                if (! preg_match('#product-events/[^/]+/(\d{4}-\d{2})/events\.jsonl\.gz$#', $file, $m)) {
                    continue;
                }
                $months[] = [
                    'month' => $m[1],
                    'path' => $file,
                    'size_bytes' => $disk->size($file),
                ];
            }
            usort($months, fn ($a, $b) => strcmp($b['month'], $a['month']));

            return $months;
        });
    }

    public function availableLandlordsForMonth(string $monthYYYYMM): array
    {
        $key = sprintf('archive:manifest:month:%s', $monthYYYYMM);

        return Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($monthYYYYMM) {
            $disk = Storage::disk('archive');
            $directories = $disk->directories('product-events');
            $landlords = [];
            foreach ($directories as $dir) {
                $landlordId = basename($dir);
                $path = sprintf('product-events/%s/%s/events.jsonl.gz', $landlordId, $monthYYYYMM);
                if ($disk->exists($path)) {
                    $landlords[] = [
                        'landlord_id' => $landlordId,
                        'path' => $path,
                        'size_bytes' => $disk->size($path),
                    ];
                }
            }

            return $landlords;
        });
    }

    public function summary(): array
    {
        $disk = Storage::disk('archive');
        $files = $disk->allFiles('product-events');
        $totalBytes = 0;
        $perLandlord = [];
        foreach ($files as $file) {
            if (! preg_match('#product-events/([^/]+)/(\d{4}-\d{2})/events\.jsonl\.gz$#', $file, $m)) {
                continue;
            }
            $size = $disk->size($file);
            $totalBytes += $size;
            $perLandlord[$m[1]] = ($perLandlord[$m[1]] ?? 0) + $size;
        }

        return [
            'total_files' => count($files),
            'total_bytes' => $totalBytes,
            'per_landlord_bytes' => $perLandlord,
        ];
    }

    public function forget(int|string $landlordId): void
    {
        Cache::forget(sprintf('archive:manifest:landlord:%s', (string) $landlordId));
    }
}
