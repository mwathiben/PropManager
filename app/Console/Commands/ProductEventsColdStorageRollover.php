<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProductEvent;
use App\Services\MetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Phase-37 PWA-RETENTION-STATS-2: monthly cold-storage rollover.
 * Dumps product_events older than config('platform.product_events_
 * cold_storage_days') to gzipped JSONL on Storage::disk('archive')
 * keyed by landlord_id / YYYY-MM / events.jsonl.gz. Runs on the
 * 1st of each month BEFORE product:prune so retention deletion
 * happens against an already-archived set.
 *
 * Idempotent — re-running for the same month is a no-op if the
 * archive file already exists.
 */
class ProductEventsColdStorageRollover extends Command
{
    protected $signature = 'product:cold-storage-rollover {--month=} {--dry-run}';

    protected $description = 'Phase-37 PWA-RETENTION-STATS-2: archive product_events to JSONL.gz before pruning.';

    public function handle(MetricsService $metrics): int
    {
        $month = $this->option('month');
        $dryRun = (bool) $this->option('dry-run');

        $monthStart = $this->resolveMonthStart($month);
        $monthEnd = $monthStart->endOfMonth();
        $monthLabel = $monthStart->format('Y-m');

        $disk = Storage::disk('archive');
        $byLandlord = [];
        $totalRows = 0;
        $totalBytes = 0;

        $this->collectEventsByLandlord($monthStart, $monthEnd, $byLandlord, $totalRows);

        $filesWritten = 0;
        foreach ($byLandlord as $landlordKey => $rows) {
            $path = sprintf('product-events/%s/%s/events.jsonl.gz', $landlordKey, $monthLabel);
            if ($disk->exists($path)) {
                continue;
            }
            $payload = $this->buildGzPayload($rows);
            if (! $dryRun) {
                $disk->put($path, $payload);
            }
            $filesWritten++;
            $totalBytes += strlen($payload);
            $metrics->gauge('archive_jsonl_bytes_total', strlen($payload), ['landlord_id' => $landlordKey]);
        }

        $metrics->gauge('archive_files_written', $filesWritten);

        $this->info(sprintf(
            'Archived %d row(s) into %d file(s) (%d bytes) for %s%s.',
            $totalRows,
            $filesWritten,
            $totalBytes,
            $monthLabel,
            $dryRun ? ' [dry-run]' : '',
        ));

        return self::SUCCESS;
    }

    /**
     * Resolve the target month start date.
     * Defaults to last calendar month when no explicit month is given.
     */
    private function resolveMonthStart(?string $month): CarbonImmutable
    {
        // Default to "last calendar month" — the cron runs on the 1st
        // so the previous month is fully closed. --month=YYYY-MM lets
        // operators re-run for older months.
        return $month
            ? CarbonImmutable::parse($month.'-01')->startOfMonth()
            : CarbonImmutable::now()->subMonth()->startOfMonth();
    }

    /**
     * Stream product_events in the date window and bucket them by landlord_id.
     *
     * @param  array<string, list<array<string, mixed>>>  $byLandlord
     */
    private function collectEventsByLandlord(
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
        array &$byLandlord,
        int &$totalRows,
    ): void {
        ProductEvent::query()
            ->withoutGlobalScopes()
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->orderBy('id')
            ->chunkById(5000, function ($rows) use (&$byLandlord, &$totalRows) {
                foreach ($rows as $row) {
                    $landlordKey = (string) ($row->landlord_id ?? 'unscoped');
                    $byLandlord[$landlordKey] ??= [];
                    $byLandlord[$landlordKey][] = [
                        'id' => $row->id,
                        'user_id' => $row->user_id,
                        'landlord_id' => $row->landlord_id,
                        'event_name' => $row->event_name,
                        'properties' => $row->properties,
                        'created_at' => $row->created_at?->toIso8601String(),
                    ];
                    $totalRows++;
                }
            });
    }

    /**
     * Encode a set of event rows as a gzip-compressed JSONL payload.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function buildGzPayload(array $rows): string
    {
        $jsonl = '';
        foreach ($rows as $row) {
            $jsonl .= json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        }

        return gzencode($jsonl, 9);
    }
}
