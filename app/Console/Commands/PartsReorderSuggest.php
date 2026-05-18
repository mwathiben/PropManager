<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DraftPurchaseOrder;
use App\Models\DraftPurchaseOrderLine;
use App\Models\Part;
use App\Services\MetricsService;
use App\Services\WorkflowLogger;
use Illuminate\Console\Command;
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
 */
class PartsReorderSuggest extends Command
{
    protected $signature = 'parts:reorder-suggest {--dry-run}';

    protected $description = 'Phase-54 PARTS-REORDER-2: materialise draft purchase orders from below-threshold parts.';

    public function handle(MetricsService $metrics, WorkflowLogger $workflowLogger): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = Part::query()
            ->withoutGlobalScope('landlord')
            ->belowThreshold()
            ->get();

        $byLandlord = $rows->groupBy('landlord_id');

        $ordersTouched = 0;
        $linesUpserted = 0;

        foreach ($byLandlord as $landlordId => $parts) {
            $byVendor = $parts->groupBy(fn (Part $part) => $this->inferVendorId($part));

            foreach ($byVendor as $vendorId => $vendorParts) {
                $vendorId = $vendorId === '' ? null : $vendorId;

                if ($dryRun) {
                    $ordersTouched++;
                    $linesUpserted += $vendorParts->count();

                    continue;
                }

                $order = DraftPurchaseOrder::withoutGlobalScope('landlord')
                    ->firstOrCreate(
                        [
                            'landlord_id' => $landlordId,
                            'suggested_vendor_id' => $vendorId,
                            'status' => DraftPurchaseOrder::STATUS_DRAFT,
                        ],
                        ['notes' => null],
                    );
                $ordersTouched++;

                foreach ($vendorParts as $part) {
                    $qty = max(1, ($part->reorder_threshold * 2) - $part->qty_available);
                    DraftPurchaseOrderLine::updateOrCreate(
                        [
                            'draft_purchase_order_id' => $order->id,
                            'part_id' => $part->id,
                        ],
                        [
                            'qty_suggested' => $qty,
                            'cost_per_unit_cents_snapshot' => (int) $part->cost_per_unit_cents,
                        ],
                    );
                    $linesUpserted++;
                }
            }

            if (! $dryRun) {
                $pendingCount = DraftPurchaseOrder::withoutGlobalScope('landlord')
                    ->where('landlord_id', $landlordId)
                    ->where('status', DraftPurchaseOrder::STATUS_DRAFT)
                    ->count();

                try {
                    $metrics->gauge('draft_purchase_orders_pending_count', (float) $pendingCount, [
                        'landlord_id' => (string) $landlordId,
                    ]);
                } catch (\Throwable) {
                    // best-effort
                }
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
