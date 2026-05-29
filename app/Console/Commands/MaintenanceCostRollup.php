<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TicketCost;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase-49 MAINTENANCE-COSTS-3: weekly rollup of per-landlord maintenance
 * spend over the last 30 days, emitted as a Prometheus gauge for ops
 * dashboards. No alert — visibility only (Phase 33 cost-attribute is the
 * model: gauge + dashboard, no paging).
 *
 * Cron: weekly Sunday 05:00 Africa/Nairobi (after Phase-33
 * storage:cost-audit at 05:00 — same Sunday cost-roll-up cluster).
 */
class MaintenanceCostRollup extends Command
{
    protected $signature = 'maintenance:cost-rollup';

    protected $description = 'Phase-49 MAINTENANCE-COSTS-3: emit landlord_maintenance_cost_kes_30d gauge.';

    public function handle(MetricsService $metrics): int
    {
        $since = now()->subDays(30);

        // Aggregate ticket_costs amount_cents joined to tickets to access
        // landlord_id. Top 50 landlords by 30-day spend.
        $rows = TicketCost::query()
            ->join('tickets', 'tickets.id', '=', 'ticket_costs.ticket_id')
            ->whereNull('ticket_costs.deleted_at')
            ->where('ticket_costs.recorded_at', '>=', $since)
            ->groupBy('tickets.landlord_id')
            ->selectRaw('tickets.landlord_id, SUM(ticket_costs.amount_cents) as total_cents')
            ->orderByDesc('total_cents')
            ->limit(50)
            ->get();

        foreach ($rows as $row) {
            $kes = round($row->total_cents / 100, 2);
            try {
                $metrics->gauge(
                    'landlord_maintenance_cost_kes_30d',
                    (float) $kes,
                    ['landlord_id' => (string) $row->landlord_id],
                );
            } catch (\Throwable $e) {
                Log::warning('maintenance:cost-rollup gauge emit failed', [
                    'landlord_id' => $row->landlord_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("maintenance:cost-rollup: {$rows->count()} landlord rollup(s) emitted");

        return self::SUCCESS;
    }
}
