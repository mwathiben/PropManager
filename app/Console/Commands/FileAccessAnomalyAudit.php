<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FileAccessAudit;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-59 ACCESS-AUDIT-3: detects download anomalies. Any user
 * exceeding config('observability.file_access_anomaly_threshold')
 * downloads in the past 5 minutes increments the
 * file_access_anomaly_count{action} gauge so ops alerts fire.
 *
 * Schedule: every 5 minutes via routes/console.php.
 */
class FileAccessAnomalyAudit extends Command
{
    protected $signature = 'file-access:anomaly-audit';

    protected $description = 'Phase-59 ACCESS-AUDIT-3: emit file_access_anomaly_count gauge on suspicious download bursts.';

    public function handle(MetricsService $metrics): int
    {
        $threshold = (int) config('observability.file_access_anomaly_threshold', 50);

        $rows = FileAccessAudit::query()
            ->where('accessed_at', '>=', now()->subMinutes(5))
            ->select('user_id', 'action', DB::raw('count(*) as hits'))
            ->groupBy('user_id', 'action')
            ->havingRaw('count(*) > ?', [$threshold])
            ->get();

        $perAction = $rows->groupBy('action')->map->count();

        foreach (['download', 'view', 'signed_url_issued'] as $action) {
            $metrics->gauge('file_access_anomaly_count', (int) ($perAction[$action] ?? 0), ['action' => $action]);
        }

        $this->info('file_access_anomaly_audit threshold='.$threshold.' anomalies='.$rows->count());

        return self::SUCCESS;
    }
}
