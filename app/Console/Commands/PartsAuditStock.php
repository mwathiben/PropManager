<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Part;
use App\Services\AlertFiringRecorder;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase-49 PARTS-INVENTORY-3: daily stock-level audit. Walks parts
 * where qty_available <= reorder_threshold AND is_active, emits
 * parts_below_threshold_count{landlord_id} gauge for top 50 landlords,
 * fires parts_below_threshold sev4 alert via AlertFiringRecorder when
 * any landlord has > 0 below-threshold parts.
 *
 * Cron: daily 06:30 Africa/Nairobi (after Phase-46 onboarding:dedupe-audit
 * at 03:30 and Phase-33 cost:attribute at 03:30, before Phase-29
 * occupancy:audit at 06:30).
 */
class PartsAuditStock extends Command
{
    protected $signature = 'parts:audit-stock';

    protected $description = 'Phase-49 PARTS-INVENTORY-3: detect parts below reorder threshold + alert.';

    public function handle(MetricsService $metrics, AlertFiringRecorder $alerts): int
    {
        $below = Part::query()
            ->withoutGlobalScope('landlord')
            ->belowThreshold()
            ->get();

        $byLandlord = $below->groupBy('landlord_id');

        foreach ($byLandlord->take(50) as $landlordId => $rows) {
            try {
                $metrics->gauge(
                    'parts_below_threshold_count',
                    (float) $rows->count(),
                    ['landlord_id' => (string) $landlordId],
                );
            } catch (\Throwable $e) {
                Log::warning('parts:audit-stock gauge emit failed', ['error' => $e->getMessage()]);
            }
        }

        if ($below->isNotEmpty()) {
            $alerts->record('parts_below_threshold', [
                'rows' => $below->count(),
                'landlords_affected' => $byLandlord->keys()->count(),
            ]);
        } else {
            $alerts->resolve('parts_below_threshold');
        }

        $this->info("parts:audit-stock: {$below->count()} below-threshold part(s) across {$byLandlord->keys()->count()} landlord(s)");

        return self::SUCCESS;
    }
}
