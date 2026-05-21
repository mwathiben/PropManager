<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RentEscalation;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase-83 RENT-ESCALATION-3: weekly per-landlord count of scheduled (pending)
 * rent escalations, emitted as a Prometheus gauge. Visibility-only — no alert
 * (mirrors documents:expiry-rollup / property:benchmark-rollup).
 */
class RentEscalationRollup extends Command
{
    protected $signature = 'rent:escalation-rollup';

    protected $description = 'Phase-83 RENT-ESCALATION-3: emit landlord_rent_escalations_scheduled gauge.';

    public function handle(MetricsService $metrics): int
    {
        $rows = RentEscalation::query()
            ->withoutGlobalScopes()
            ->scheduled()
            ->selectRaw('landlord_id, COUNT(*) as cnt')
            ->groupBy('landlord_id')
            ->get();

        $emitted = 0;
        foreach ($rows as $row) {
            try {
                $metrics->gauge(
                    'landlord_rent_escalations_scheduled',
                    (float) $row->cnt,
                    ['landlord_id' => (string) $row->landlord_id],
                );
                $emitted++;
            } catch (\Throwable $e) {
                Log::warning('rent:escalation-rollup gauge emit failed', [
                    'landlord_id' => $row->landlord_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("rent:escalation-rollup: {$emitted} landlord gauge(s) emitted");

        return self::SUCCESS;
    }
}
