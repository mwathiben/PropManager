<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DraftPurchaseOrder;
use App\Models\DraftPurchaseOrderLine;
use App\Models\Part;
use App\Services\Maintenance\PartUsageService;
use App\Services\MetricsService;
use App\Services\WorkflowLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase-54 PARTS-REORDER-2: convert the Phase-49 parts_below_threshold
 * signal into a draft purchase order per (landlord, suggested vendor).
 *
 * Vendor inference (parts.vendor_id does NOT exist by design — Phase-49
 * keeps Part landlord-scoped without a default vendor):
 *   Walk the latest ticket_parts pivot rows that referenced this part,
 *   take the most recent ticket.vendor_id. NULL when no history.
 *
 * Scheduled daily 06:45 Africa/Nairobi (after parts:audit-stock 06:30,
 * before tickets:audit-sla 07:00).
 *
 * Idempotent: existing draft rows for (landlord, vendor, status=draft)
 * are reused via the dpo_unique_open_per_vendor index; lines are
 * upserted per part_id so re-runs don't duplicate.
 *
 * Phase-75 PARTS-PREDICT-2: triggers on the EFFECTIVE threshold —
 * reorder_threshold + ceil(lead_time_days * daily usage rate) — so a part
 * still above its static threshold but projected to run out before a
 * replacement arrives is ordered early. Each line records WHY it fired
 * (static vs lead_time_buffer) + the projected stockout date.
 */
class PartsReorderSuggest extends Command
{
    protected $signature = 'parts:reorder-suggest {--dry-run}';

    protected $description = 'Phase-54 PARTS-REORDER-2: materialise draft purchase orders from below-threshold parts.';

    public function handle(MetricsService $metrics, WorkflowLogger $workflowLogger, PartUsageService $usage): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $activeByLandlord = $this->fetchActivePartsByLandlord();

        $ordersTouched = 0;
        $linesUpserted = 0;

        foreach ($activeByLandlord as $landlordId => $allParts) {
            $rates = $usage->dailyRatesFor((int) $landlordId, $allParts);

            $parts = $this->filterBelowEffectiveThreshold($allParts, $rates);

            if ($parts->isEmpty()) {
                continue;
            }

            [$touched, $upserted] = $this->processLandlordParts($parts, $rates, (int) $landlordId, $dryRun);
            $ordersTouched += $touched;
            $linesUpserted += $upserted;

            if (! $dryRun) {
                $this->emitMetrics($metrics, (int) $landlordId, $parts);
            }
        }

        $this->info(sprintf(
            'parts:reorder-suggest: %d order(s) touched, %d line(s) upserted%s',
            $ordersTouched,
            $linesUpserted,
            $dryRun ? ' (dry-run)' : '',
        ));

        $workflowLogger->log(
            workflowName: 'parts:reorder-suggest',
            action: 'completed',
            metadata: ['orders' => $ordersTouched, 'lines' => $linesUpserted, 'dry_run' => $dryRun],
        );

        return self::SUCCESS;
    }

    /**
     * Coarse pre-filter so we only hydrate parts that could possibly trigger:
     * below the static threshold, OR with consumption in the usage window. A
     * part above its threshold with zero recent usage has rate 0, so its
     * effective threshold equals the static one and it can never trip the
     * lead-time buffer — no need to load it.
     */
    private function fetchActivePartsByLandlord(): Collection
    {
        $windowStart = now()->subDays(PartUsageService::DEFAULT_WINDOW_DAYS);

        return Part::query()
            ->withoutGlobalScope('landlord')
            ->where('is_active', true)
            ->where(function ($query) use ($windowStart) {
                $query
                    ->whereColumn('qty_available', '<=', 'reorder_threshold')
                    ->orWhereExists(function ($sub) use ($windowStart) {
                        $sub->from('ticket_parts')
                            ->join('tickets', 'tickets.id', '=', 'ticket_parts.ticket_id')
                            ->whereColumn('ticket_parts.part_id', 'parts.id')
                            ->whereColumn('tickets.landlord_id', 'parts.landlord_id')
                            ->where('ticket_parts.recorded_at', '>=', $windowStart);
                    });
            })
            ->with('suppliers:id,part_id,vendor_id,unit_cost_cents,lead_time_days')
            ->get()
            ->groupBy('landlord_id');
    }

    /**
     * Keep only parts that are below their effective threshold (static or
     * lead-time-buffered).
     */
    private function filterBelowEffectiveThreshold(Collection $allParts, array $rates): Collection
    {
        return $allParts
            ->filter(fn (Part $part) => $part->belowEffectiveThreshold($rates[$part->id] ?? 0.0, $part->leadTimeDays()))
            ->values();
    }

    /**
     * Group parts by inferred vendor, then create/update orders and lines.
     * Returns [ordersTouched, linesUpserted].
     */
    private function processLandlordParts(Collection $parts, array $rates, int $landlordId, bool $dryRun): array
    {
        $ordersTouched = 0;
        $linesUpserted = 0;

        $byVendor = $parts->groupBy(fn (Part $part) => $this->inferVendorId($part));

        foreach ($byVendor as $vendorId => $vendorParts) {
            $vendorId = $vendorId === '' ? null : $vendorId;

            if ($dryRun) {
                $ordersTouched++;
                $linesUpserted += $vendorParts->count();

                continue;
            }

            $order = $this->findOrCreateDraftOrder($landlordId, $vendorId);
            $ordersTouched++;

            $linesUpserted += $this->upsertOrderLines($order->id, $vendorParts, $rates);
        }

        return [$ordersTouched, $linesUpserted];
    }

    /**
     * Find or create the draft purchase order for a (landlord, vendor) pair.
     */
    private function findOrCreateDraftOrder(int $landlordId, int|string|null $vendorId): DraftPurchaseOrder
    {
        return DraftPurchaseOrder::withoutGlobalScope('landlord')
            ->firstOrCreate(
                [
                    'landlord_id' => $landlordId,
                    'suggested_vendor_id' => $vendorId,
                    'status' => DraftPurchaseOrder::STATUS_DRAFT,
                ],
                ['notes' => null],
            );
    }

    /**
     * Upsert all lines for a vendor group. Returns number of lines upserted.
     */
    private function upsertOrderLines(int $orderId, Collection $vendorParts, array $rates): int
    {
        foreach ($vendorParts as $part) {
            $rate = $rates[$part->id] ?? 0.0;
            $buffer = (int) ceil($part->leadTimeDays() * $rate);
            $qty = max(1, ($part->reorder_threshold * 2) - $part->qty_available + $buffer);

            DraftPurchaseOrderLine::updateOrCreate(
                [
                    'draft_purchase_order_id' => $orderId,
                    'part_id' => $part->id,
                ],
                [
                    'qty_suggested' => $qty,
                    'cost_per_unit_cents_snapshot' => (int) $part->cost_per_unit_cents,
                    'trigger_reason' => $part->isBelowThreshold()
                        ? DraftPurchaseOrderLine::REASON_STATIC
                        : DraftPurchaseOrderLine::REASON_LEAD_TIME,
                    'projected_stockout_at' => $part->projectedStockoutDate($rate),
                ],
            );
        }

        return $vendorParts->count();
    }

    /**
     * Emit best-effort gauges for pending orders and predicted stockouts.
     */
    private function emitMetrics(MetricsService $metrics, int $landlordId, Collection $parts): void
    {
        $pendingCount = DraftPurchaseOrder::withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->where('status', DraftPurchaseOrder::STATUS_DRAFT)
            ->count();

        $predictedCount = $parts->reject->isBelowThreshold()->count();

        try {
            $metrics->gauge('draft_purchase_orders_pending_count', (float) $pendingCount, [
                'landlord_id' => (string) $landlordId,
            ]);
            $metrics->gauge('parts_predicted_stockout_count', (float) $predictedCount, [
                'landlord_id' => (string) $landlordId,
            ]);
        } catch (\Throwable) {
            // best-effort
        }
    }

    /**
     * Latest-ticket-wins heuristic for the suggested vendor. Returns
     * null when this part has no ticket history with a vendor.
     */
    private function inferVendorId(Part $part): ?int
    {
        return DB::table('ticket_parts')
            ->join('tickets', 'tickets.id', '=', 'ticket_parts.ticket_id')
            ->where('ticket_parts.part_id', $part->id)
            ->whereNotNull('tickets.vendor_id')
            ->orderByDesc('ticket_parts.recorded_at')
            ->value('tickets.vendor_id');
    }
}
