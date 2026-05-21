<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Events\TicketAssignedToVendor;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\Vendor;
use App\Services\Vendors\VendorPerformanceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase-49 VENDOR-MARKETPLACE-2: canonical write path for assigning an
 * external Vendor (contractor) to a Ticket. Writes vendor_id under
 * DB::transaction + logs a TicketActivity row + fires
 * TicketAssignedToVendor.
 *
 * Vendor must belong to the same landlord as the ticket — guards
 * against cross-tenant data leaks.
 */
class VendorAssignmentService
{
    public function __construct(
        private VendorPerformanceService $performance,
    ) {}

    /**
     * Phase-75 VENDOR-ROUTING-2: vendors who can take this ticket, best first.
     * Filters the landlord's active vendors to those whose specialty matches the
     * ticket's subcategory (falls back to all active when none match), then
     * ranks by within-SLA % desc, open-overdue asc.
     *
     * @return Collection<int, array{vendor_id:int, name:string, within_sla_pct:?float, open_overdue:int, matched:bool}>
     */
    public function suggestPool(Ticket $ticket): Collection
    {
        $landlordId = (int) $ticket->landlord_id;
        $category = (string) $ticket->subcategory;

        $matching = Vendor::query()
            ->where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->whereHas('specialties', fn ($q) => $q->where('category', $category))
            ->get(['id', 'name']);

        $matched = $matching->isNotEmpty();
        $pool = $matched
            ? $matching
            : Vendor::query()->where('landlord_id', $landlordId)->where('is_active', true)->get(['id', 'name']);

        $perf = collect($this->performance->forLandlord($landlordId))->keyBy('vendor_id');

        return $pool
            ->map(fn (Vendor $v) => [
                'vendor_id' => $v->id,
                'name' => $v->name,
                'within_sla_pct' => $perf[$v->id]['within_sla_pct'] ?? null,
                'open_overdue' => (int) ($perf[$v->id]['open_overdue'] ?? 0),
                'matched' => $matched,
            ])
            ->sort(function (array $a, array $b) {
                // within_sla_pct desc (null = worst), then open_overdue asc.
                $sa = $a['within_sla_pct'] ?? -1.0;
                $sb = $b['within_sla_pct'] ?? -1.0;
                if ($sa !== $sb) {
                    return $sb <=> $sa;
                }

                return $a['open_overdue'] <=> $b['open_overdue'];
            })
            ->values();
    }

    /**
     * Phase-75 VENDOR-ROUTING-3: opt-in auto-route. Assigns the top suggested
     * vendor when auto-routing is on, the ticket has no vendor yet, and the pool
     * is non-empty. Returns null (no-op) otherwise — never overrides a manual
     * assignment.
     */
    public function autoAssign(Ticket $ticket): ?Ticket
    {
        if (! config('maintenance.auto_route_vendors', false)) {
            return null;
        }
        if ($ticket->vendor_id !== null) {
            return null;
        }

        $top = $this->suggestPool($ticket)->first();
        if ($top === null) {
            return null;
        }

        $vendor = Vendor::query()
            ->where('landlord_id', $ticket->landlord_id)
            ->find($top['vendor_id']);

        return $vendor ? $this->assign($ticket, $vendor, 'Auto-routed') : null;
    }

    public function assign(Ticket $ticket, Vendor $vendor, ?string $note = null): Ticket
    {
        if ($vendor->landlord_id !== $ticket->landlord_id) {
            throw new InvalidArgumentException(
                "Vendor {$vendor->id} (landlord {$vendor->landlord_id}) cannot be assigned to ticket {$ticket->id} (landlord {$ticket->landlord_id})."
            );
        }

        return DB::transaction(function () use ($ticket, $vendor, $note) {
            $previousVendorId = $ticket->vendor_id;
            // Phase-70 TICKET-INBOX: a (re)assignment resets the vendor's
            // accept/decline state to pending awaiting their response.
            $ticket->update([
                'vendor_id' => $vendor->id,
                'vendor_status' => 'pending',
                'vendor_responded_at' => null,
            ]);

            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'landlord_id' => $ticket->landlord_id,
                'user_id' => Auth::id(),
                'action' => 'vendor_assigned',
                'old_value' => $previousVendorId !== null ? (string) $previousVendorId : null,
                'new_value' => (string) $vendor->id,
                'description' => $note,
                'created_at' => now(),
            ]);

            TicketAssignedToVendor::dispatch($ticket, $vendor, $note);

            return $ticket->fresh();
        });
    }
}
