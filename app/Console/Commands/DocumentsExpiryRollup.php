<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase-82 DOC-EXPIRY-3: weekly per-landlord count of documents expiring within
 * 30 days, emitted as a Prometheus gauge for ops dashboards. Visibility-only —
 * no alert (mirrors property:benchmark-rollup / caretaker:performance-rollup).
 */
class DocumentsExpiryRollup extends Command
{
    protected $signature = 'documents:expiry-rollup';

    protected $description = 'Phase-82 DOC-EXPIRY-3: emit landlord_documents_expiring_30d gauge.';

    public function handle(MetricsService $metrics): int
    {
        $rows = Document::query()
            ->withoutGlobalScopes()
            ->current()
            ->expiringSoon(30)
            ->where('expires_at', '>=', now()->subYears(1)->toDateString())
            ->selectRaw('landlord_id, COUNT(*) as cnt')
            ->groupBy('landlord_id')
            ->get();

        $emitted = 0;
        foreach ($rows as $row) {
            try {
                $metrics->gauge(
                    'landlord_documents_expiring_30d',
                    (float) $row->cnt,
                    ['landlord_id' => (string) $row->landlord_id],
                );
                $emitted++;
            } catch (\Throwable $e) {
                Log::warning('documents:expiry-rollup gauge emit failed', [
                    'landlord_id' => $row->landlord_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("documents:expiry-rollup: {$emitted} landlord gauge(s) emitted");

        return self::SUCCESS;
    }
}
