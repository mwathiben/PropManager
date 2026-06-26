<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StorageTierPolicy;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Phase-33 COST-STORAGE-2: walk every active StorageTierPolicy,
 * bucket files under (prefix) by age into current|target tiers, and
 * emit storage_bytes_by_tier_total + storage_files_by_tier_total
 * gauges. Heavy I/O (filesystem walk per disk) — schedule weekly.
 *
 * We DO NOT auto-move objects. An operator reads the gauges and
 * applies a real S3 LIFECYCLE rule, which AWS executes atomically.
 * Auto-moving from a cron would risk data loss + duplicate-bill
 * during a partial move.
 */
class StorageTierPolicyAudit extends Command
{
    protected $signature = 'storage:tier-policy {--disk= : limit to one disk}';

    protected $description = 'Phase-33 COST-STORAGE-2: per-policy bytes/files by age bucket (current vs target tier).';

    public function handle(MetricsService $metrics): int
    {
        $diskFilter = $this->option('disk');
        $query = StorageTierPolicy::query()->where('is_active', true);
        if ($diskFilter) {
            $query->where('disk_name', $diskFilter);
        }
        $policies = $query->get();
        if ($policies->isEmpty()) {
            $this->warn('No active storage tier policies — run db:seed --class=Database\\Seeders\\Phase33StorageTierPolicySeeder');

            return self::SUCCESS;
        }

        foreach ($policies as $policy) {
            $this->auditPolicy($policy, $metrics);
        }

        $this->info(sprintf('Audited %d policy/policies.', $policies->count()));

        return self::SUCCESS;
    }

    private function auditPolicy(StorageTierPolicy $policy, MetricsService $metrics): void
    {
        $disk = $this->resolveDisk($policy);
        if ($disk === null) {
            return;
        }

        $files = $this->resolveFiles($policy, $disk);
        if ($files === null) {
            return;
        }

        $cutoff = Carbon::now()->subDays($policy->max_age_days)->timestamp;
        $buckets = $this->classifyFiles($disk, $files, $cutoff);

        $this->emitMetrics($metrics, $policy, $buckets);

        $this->line(sprintf(
            '%-12s %-24s current=%dB/%dfiles target=%dB/%dfiles',
            $policy->disk_name,
            $policy->path_prefix,
            $buckets['current']['bytes'], $buckets['current']['files'],
            $buckets['target']['bytes'], $buckets['target']['files'],
        ));
    }

    private function resolveDisk(StorageTierPolicy $policy): mixed
    {
        try {
            return Storage::disk($policy->disk_name);
        } catch (\Throwable $e) {
            $this->warn(sprintf('Disk [%s] unavailable: %s', $policy->disk_name, $e->getMessage()));

            return null;
        }
    }

    private function resolveFiles(StorageTierPolicy $policy, mixed $disk): ?array
    {
        try {
            return $disk->allFiles($policy->path_prefix);
        } catch (\Throwable $e) {
            $this->warn(sprintf('Disk [%s] walk failed for [%s]: %s', $policy->disk_name, $policy->path_prefix, $e->getMessage()));

            return null;
        }
    }

    private function classifyFiles(mixed $disk, array $files, int $cutoff): array
    {
        $buckets = [
            'current' => ['bytes' => 0, 'files' => 0],
            'target' => ['bytes' => 0, 'files' => 0],
        ];

        foreach ($files as $file) {
            $size = $this->safeSize($disk, $file);
            $mtime = $this->safeMtime($disk, $file);
            $bucket = ($mtime !== null && $mtime < $cutoff) ? 'target' : 'current';
            $buckets[$bucket]['bytes'] += $size;
            $buckets[$bucket]['files']++;
        }

        return $buckets;
    }

    private function emitMetrics(MetricsService $metrics, StorageTierPolicy $policy, array $buckets): void
    {
        $labels = [
            'disk' => $policy->disk_name,
            'prefix' => trim($policy->path_prefix, '/'),
            'target_tier' => $policy->target_tier,
        ];

        foreach ($buckets as $bucket => $counts) {
            $bucketLabels = $labels + ['bucket' => $bucket];
            $metrics->gauge('storage_bytes_by_tier_total', (float) $counts['bytes'], $bucketLabels);
            $metrics->gauge('storage_files_by_tier_total', (float) $counts['files'], $bucketLabels);
        }
    }

    private function safeSize($disk, string $file): int
    {
        try {
            return (int) $disk->size($file);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeMtime($disk, string $file): ?int
    {
        try {
            return (int) $disk->lastModified($file);
        } catch (\Throwable) {
            return null;
        }
    }
}
