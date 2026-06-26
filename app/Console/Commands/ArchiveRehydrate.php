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

        $monthLabel = $this->resolveMonthLabel();
        if ($monthLabel === null) {
            return self::FAILURE;
        }

        $path = sprintf('product-events/%s/%s/events.jsonl.gz', $landlord, $monthLabel);

        if (! $this->archiveFileExists($path)) {
            return self::FAILURE;
        }

        if ($this->option('clear-first')) {
            $this->clearExistingRows($path);
        }

        $jsonl = $this->decodeArchive($path);
        if ($jsonl === null) {
            return self::FAILURE;
        }

        $inserted = $this->insertBatches($jsonl, $path);

        $metrics->gauge('archive_rows_rehydrated_count', $inserted, ['landlord_id' => $landlord, 'month' => $monthLabel]);

        $this->info(sprintf(
            'Rehydrated %d row(s) from %s. Query SELECT * FROM rehydrated_product_events WHERE source_path = %s.',
            $inserted,
            $path,
            "'".$path."'",
        ));

        return self::SUCCESS;
    }

    private function resolveMonthLabel(): ?string
    {
        $month = $this->option('month');
        if (! $month) {
            $this->error('--month=YYYY-MM is required.');

            return null;
        }

        try {
            $parsed = CarbonImmutable::parse($month.'-01');
        } catch (\Throwable $e) {
            $this->error('Invalid --month — expected YYYY-MM.');

            return null;
        }

        return $parsed->format('Y-m');
    }

    private function archiveFileExists(string $path): bool
    {
        if (! Storage::disk('archive')->exists($path)) {
            $this->error("Archive file not found: {$path}");

            return false;
        }

        return true;
    }

    private function clearExistingRows(string $path): void
    {
        RehydratedProductEvent::query()->withoutGlobalScopes()->where('source_path', $path)->delete();
    }

    private function decodeArchive(string $path): ?string
    {
        $payload = Storage::disk('archive')->get($path);
        $jsonl = @gzdecode((string) $payload);

        if ($jsonl === false) {
            $this->error("Failed to gunzip archive file: {$path}");

            return null;
        }

        return $jsonl;
    }

    private function insertBatches(string $jsonl, string $path): int
    {
        $now = now();
        $batch = [];
        $inserted = 0;

        foreach (preg_split('/\r?\n/', $jsonl) ?: [] as $line) {
            $row = $this->parseLine($line);
            if ($row === null) {
                continue;
            }

            $batch[] = $this->buildRow($row, $path, $now);

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

        return $inserted;
    }

    private function parseLine(string $line): ?array
    {
        if ($line === '') {
            return null;
        }

        $row = json_decode($line, true);

        return is_array($row) ? $row : null;
    }

    private function buildRow(array $row, string $path, mixed $now): array
    {
        return [
            'original_id' => $row['id'] ?? null,
            'user_id' => $row['user_id'] ?? null,
            'landlord_id' => $row['landlord_id'] ?? null,
            'event_name' => substr((string) ($row['event_name'] ?? 'unknown'), 0, 64),
            'properties' => isset($row['properties']) ? json_encode($row['properties']) : null,
            'original_created_at' => $row['created_at'] ?? null,
            'rehydrated_at' => $now,
            'source_path' => $path,
        ];
    }
}
