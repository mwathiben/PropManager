<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Ticket;
use App\Models\TicketCost;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Phase-49 MAINTENANCE-COSTS-2: canonical write path for ticket-level
 * cost attribution. Validates category + non-negative amount + recorder
 * belongs to the ticket's landlord.
 *
 * recordPartsAggregate() is the idempotent upsert used by
 * TicketResolutionService::recordParts to seed a single 'parts'-category
 * row whose amount_cents stays in sync with the ticket_parts pivot sum.
 */
class TicketCostService
{
    public function recordCost(Ticket $ticket, string $category, int $amountCents, ?string $notes = null, ?User $recordedBy = null): TicketCost
    {
        if (! in_array($category, TicketCost::CATEGORIES, true)) {
            throw new InvalidArgumentException("Unsupported ticket cost category: {$category}");
        }
        if ($amountCents < 0) {
            throw new InvalidArgumentException("Ticket cost amount must be non-negative; got {$amountCents}.");
        }

        $recorder = $recordedBy ?? Auth::user();
        if ($recorder !== null && $recorder->landlord_id !== null && $recorder->landlord_id !== $ticket->landlord_id) {
            throw new InvalidArgumentException(
                "Recorder {$recorder->id} (landlord {$recorder->landlord_id}) cannot record cost on ticket {$ticket->id} (landlord {$ticket->landlord_id})."
            );
        }

        return TicketCost::create([
            'ticket_id' => $ticket->id,
            'category' => $category,
            'amount_cents' => $amountCents,
            'currency' => 'KES',
            'recorded_by' => $recorder?->id,
            'notes' => $notes,
            'recorded_at' => now(),
        ]);
    }

    public function recordPartsAggregate(Ticket $ticket, int $totalCostCents): TicketCost
    {
        return TicketCost::updateOrCreate(
            [
                'ticket_id' => $ticket->id,
                'category' => TicketCost::CATEGORY_PARTS,
            ],
            [
                'amount_cents' => $totalCostCents,
                'currency' => 'KES',
                'recorded_by' => Auth::id(),
                'recorded_at' => now(),
            ],
        );
    }

    /**
     * @return array{parts:int, vendor:int, labor:int, other:int, total:int}
     */
    public function summarize(Ticket $ticket): array
    {
        $rows = TicketCost::query()
            ->where('ticket_id', $ticket->id)
            ->select('category', 'amount_cents')
            ->get()
            ->groupBy('category')
            ->map(fn ($group) => (int) $group->sum('amount_cents'));

        $summary = [
            'parts' => (int) ($rows[TicketCost::CATEGORY_PARTS] ?? 0),
            'vendor' => (int) ($rows[TicketCost::CATEGORY_VENDOR] ?? 0),
            'labor' => (int) ($rows[TicketCost::CATEGORY_LABOR] ?? 0),
            'other' => (int) ($rows[TicketCost::CATEGORY_OTHER] ?? 0),
        ];
        $summary['total'] = array_sum($summary);

        return $summary;
    }
}
