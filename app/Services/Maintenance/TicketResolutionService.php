<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Part;
use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase-49 PARTS-INVENTORY-2 / MAINTENANCE-COSTS-2: canonical write path
 * for recording parts consumed during a ticket resolution.
 *
 * recordParts($ticket, [part_id => qty, ...]) walks each pair, pulls the
 * Part row with lockForUpdate, validates qty_available >= qty, decrements
 * stock, inserts the ticket_parts pivot row with cost_allocated_cents
 * snapshotted from Part.cost_per_unit_cents × qty.
 *
 * After all parts are recorded, idempotently upserts a single ticket_costs
 * row of category 'parts' with the total — Phase 49 MAINTENANCE-COSTS-2
 * wires this in once TicketCostService lands.
 *
 * Throws ValidationException on insufficient stock — caller (controller)
 * should surface as 422.
 */
class TicketResolutionService
{
    public function __construct(
        protected TicketCostService $costService,
    ) {}

    /**
     * @param  array<int|string, int>  $partUsage  map of part_id => qty_used
     */
    public function recordParts(Ticket $ticket, array $partUsage): array
    {
        if (empty($partUsage)) {
            return ['rows_inserted' => 0, 'total_cost_cents' => 0];
        }

        return DB::transaction(function () use ($ticket, $partUsage) {
            $rowsInserted = 0;
            $totalCostCents = 0;
            $recorderId = Auth::id();
            $recordedAt = now();

            foreach ($partUsage as $partId => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) {
                    continue;
                }

                $allocatedCents = $this->consumeOnePart($ticket, $partId, $qty, ['recorder_id' => $recorderId, 'recorded_at' => $recordedAt]);
                $totalCostCents += $allocatedCents;
                $rowsInserted++;
            }

            // Phase-49 MAINTENANCE-COSTS-2: idempotent 'parts'-category row.
            if ($totalCostCents > 0) {
                $this->costService->recordPartsAggregate($ticket, $totalCostCents);
            }

            return ['rows_inserted' => $rowsInserted, 'total_cost_cents' => $totalCostCents];
        });
    }

    /**
     * Lock, validate, decrement stock for one part, insert pivot row, and return cost in cents.
     */
    /**
     * @param  array{recorder_id: int|null, recorded_at: \Illuminate\Support\Carbon}  $meta
     */
    private function consumeOnePart(Ticket $ticket, int|string $partId, int $qty, array $meta): int
    {
        $part = Part::query()
            ->where('id', $partId)
            ->where('landlord_id', $ticket->landlord_id)
            ->lockForUpdate()
            ->first();

        if ($part === null) {
            throw ValidationException::withMessages([
                'parts' => "Part {$partId} not found or not owned by landlord.",
            ]);
        }

        if ($part->qty_available < $qty) {
            throw ValidationException::withMessages([
                'parts' => "Part '{$part->name}' has {$part->qty_available} available; cannot use {$qty}.",
            ]);
        }

        $part->decrement('qty_available', $qty);

        $allocatedCents = $part->cost_per_unit_cents * $qty;

        DB::table('ticket_parts')->insert([
            'ticket_id' => $ticket->id,
            'part_id' => $part->id,
            'qty_used' => $qty,
            'cost_allocated_cents' => $allocatedCents,
            'recorded_by' => $meta['recorder_id'],
            'recorded_at' => $meta['recorded_at'],
            'created_at' => $meta['recorded_at'],
            'updated_at' => $meta['recorded_at'],
        ]);

        return $allocatedCents;
    }
}
