<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\ScheduledReportDelivery;
use App\Models\ScheduledReport;
use App\Services\Reports\ReportBuilderService;
use App\Services\Reports\XlsxExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-27 BI-DELIVERY-2: dispatch due scheduled reports.
 *
 * Scheduled via routes/console.php to run dailyAt('06:00'). For each
 * scheduled_reports row where next_due_at <= now() and the saved
 * report still exists:
 *   1. Run the report config through ReportBuilderService (returns rows)
 *   2. Render the rows to a temp xlsx via XlsxExportService
 *   3. Queue ScheduledReportDelivery mailable with the xlsx attached
 *   4. ScheduledReport::markSent() advances next_due_at by the cadence
 *
 * Errors per row are logged but don't halt the batch — one bad
 * config can't break the rest of the day's deliveries.
 */
class SendScheduledReports extends Command
{
    protected $signature = 'reports:send-scheduled
        {--dry-run : List due reports without sending them}';

    protected $description = 'Phase-27 BI-DELIVERY-2: dispatch any scheduled reports whose next_due_at has passed.';

    public function handle(ReportBuilderService $builder, XlsxExportService $xlsx): int
    {
        $due = ScheduledReport::query()
            ->where('next_due_at', '<=', Carbon::now())
            ->with('savedReport')
            ->get();

        $this->info("Found {$due->count()} scheduled report(s) due now.");

        if ($this->option('dry-run')) {
            foreach ($due as $row) {
                $name = $row->savedReport?->name ?? '(deleted)';
                $this->line("  would send {$row->id} → {$row->recipient_email} ({$name})");
            }

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($due as $schedule) {
            try {
                if (! $schedule->savedReport) {
                    $this->warn("Schedule {$schedule->id} references a deleted saved report — skipping.");

                    continue;
                }

                $rows = $builder->run((array) $schedule->savedReport->config, (int) $schedule->landlord_id);
                $tempPath = $this->renderXlsx($xlsx, $schedule, $rows);

                Mail::to($schedule->recipient_email)
                    ->queue(new ScheduledReportDelivery($schedule, $tempPath));

                $schedule->markSent();
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Schedule {$schedule->id} failed: ".$e->getMessage());
                report($e);
            }
        }

        $this->info("Sent: {$sent}. Failed: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Render the rows to a temp xlsx file. The mailable attaches it +
     * the post-send Laravel cleanup unlinks tmp files.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function renderXlsx(XlsxExportService $xlsx, ScheduledReport $schedule, array $rows): string
    {
        $columns = $this->inferColumns($rows);
        $tmpDir = storage_path('app/tmp/scheduled-reports');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0o755, true);
        }
        $path = $tmpDir.'/'.$schedule->id.'-'.now()->format('YmdHis').'.xlsx';

        $xlsx->write(
            $schedule->savedReport->name ?? 'Report',
            $columns,
            $rows,
            $path,
        );

        return $path;
    }

    /**
     * Infer xlsx column metadata from the first row. The
     * ReportBuilderService aliases columns like
     * `payment_amount`/`invoice_due_date` — we map those back to
     * (label, type) by splitting on `_` and pattern-matching the tail.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{label: string, key: string, type: 'string'|'currency'|'integer'|'date'}>
     */
    private function inferColumns(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $first = $rows[0];
        $columns = [];
        foreach (array_keys($first) as $key) {
            $columns[] = [
                'label' => $this->humanise((string) $key),
                'key' => (string) $key,
                'type' => $this->inferType((string) $key, $first[$key]),
            ];
        }

        return $columns;
    }

    private function humanise(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    private function inferType(string $key, mixed $value): string
    {
        if (str_contains($key, 'amount') || str_contains($key, 'rent') || str_contains($key, 'paid') || str_contains($key, 'total') || str_contains($key, 'due')) {
            return str_ends_with($key, 'date') ? 'date' : 'currency';
        }
        if (str_ends_with($key, 'date') || str_ends_with($key, '_at')) {
            return 'date';
        }
        if (is_int($value)) {
            return 'integer';
        }

        return 'string';
    }
}
