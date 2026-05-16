<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RehydratedProductEvent;
use App\Services\MetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ArchiveRehydrate extends Command
{
    protected $signature = 'archive:rehydrate {--landlord= : landlord_id (defaults to "unscoped")} {--month= : YYYY-MM} {--clear-first}';

    protected $description = 'Phase-39 RETENTION-READ-1: rehydrate archived product_events JSONL.gz into rehydrated_product_events for ad-hoc query.';

    public function handle(MetricsService $metrics): int
    {
        $landlord = (string) ($this->option('landlord') ?? 'unscoped');
        $month = $this->option('month');
        if (! $month) {
            $this->error('--month=YYYY-MM is required.');

            return self::FAILURE;
        }

        try {
            $parsed = CarbonImmutable::parse($month.'-01');
        } catch (\Throwable $e) {
            $this->error('Invalid --month — expected YYYY-MM.');

            return self::FAILURE;
        }
        $monthLabel = $parsed->format('Y-m');

        $path = sprintf('product-events/%s/%s/events.jsonl.gz', $landlord, $monthLabel);
        $disk = Storage::disk('archive');
        if (! $disk->exists($path)) {
            $this->error("Archive file not found: {$path}");

            return self::FAILURE;
        }

        if ($this->option('clear-first')) {
            RehydratedProductEvent::query()->withoutGlobalScopes()->where('source_path', $path)->delete();
        }

        $payload = $disk->get($path);
        $jsonl = @gzdecode((string) $payload);
        if ($jsonl === false) {
            $this->error("Failed to gunzip archive file: {$path}");

            return self::FAILURE;
        }

        $now = now();
        $batch = [];
        $inserted = 0;
        foreach (preg_split('/\r?\n/', $jsonl) ?: [] as $line) {
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (! is_array($row)) {
                continue;
            }
            $batch[] = [
                'original_id' => $row['id'] ?? null,
                'user_id' => $row['user_id'] ?? null,
                'landlord_id' => $row['landlord_id'] ?? null,
                'event_name' => substr((string) ($row['event_name'] ?? 'unknown'), 0, 64),
                'properties' => isset($row['properties']) ? json_encode($row['properties']) : null,
                'original_created_at' => $row['created_at'] ?? null,
                'rehydrated_at' => $now,
                'source_path' => $path,
            ];
            if (count($batch) >= 1000) {
                RehydratedProductEvent::query()->insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            RehydratedProductEvent::query()->insert($batch);
            $inserted += count($batch);
        }

        $metrics->gauge('archive_rows_rehydrated_count', $inserted, ['landlord_id' => $landlord, 'month' => $monthLabel]);

        $this->info(sprintf(
            'Rehydrated %d row(s) from %s. Query SELECT * FROM rehydrated_product_events WHERE source_path = %s.',
            $inserted,
            $path,
            "'".$path."'",
        ));

        return self::SUCCESS;
    }
}
