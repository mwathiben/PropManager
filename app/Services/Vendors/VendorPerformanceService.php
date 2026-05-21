<?php

declare(strict_types=1);

namespace App\Services\Vendors;

use App\Models\Ticket;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

/**
 * Phase-75 VENDOR-PERF-1: landlord-side vendor comparison. Same resolution-
 * within-SLA definition as VendorSlaService (resolved_at <= resolution_due_at)
 * but computed for ALL of a landlord's active vendors at once, plus cost per
 * resolved ticket from ticket_costs. Batched (no per-vendor query / no N+1).
 */
class VendorPerformanceService
{
    /**
     * @return list<array{
     *   vendor_id:int, name:string, resolved_count:int, with_due:int,
     *   within_sla:int, within_sla_pct:?float, avg_resolution_hours:?float,
     *   open_overdue:int, cost_total_cents:int, cost_per_ticket_cents:?int
     * }>
     */
    public function forLandlord(int $landlordId, int $windowDays = 90): array
    {
        $windowDays = max(1, $windowDays);
        $since = now()->subDays($windowDays);

        $vendors = Vendor::query()
            ->where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($vendors->isEmpty()) {
            return [];
        }

        $ids = $vendors->pluck('id')->all();

        // Resolved-in-window tickets, grouped by vendor in memory.
        $resolvedByVendor = Ticket::query()
            ->where('landlord_id', $landlordId)
            ->whereIn('vendor_id', $ids)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $since)
            ->get(['vendor_id', 'created_at', 'resolved_at', 'resolution_due_at'])
            ->groupBy('vendor_id');

        // Open tickets currently past their resolution SLA, counted per vendor.
        $overdueByVendor = Ticket::query()
            ->where('landlord_id', $landlordId)
            ->whereIn('vendor_id', $ids)
            ->breachedResolutionSla()
            ->get(['vendor_id'])
            ->countBy('vendor_id');

        // Total cost of resolved-in-window tickets, per vendor (cents).
        $costByVendor = DB::table('ticket_costs')
            ->join('tickets', 'tickets.id', '=', 'ticket_costs.ticket_id')
            ->whereNull('ticket_costs.deleted_at')
            ->where('tickets.landlord_id', $landlordId)
            ->whereIn('tickets.vendor_id', $ids)
            ->whereNotNull('tickets.resolved_at')
            ->where('tickets.resolved_at', '>=', $since)
            ->groupBy('tickets.vendor_id')
            ->selectRaw('tickets.vendor_id as vendor_id, COALESCE(SUM(ticket_costs.amount_cents),0) as total')
            ->pluck('total', 'vendor_id');

        return $vendors->map(function (Vendor $vendor) use ($resolvedByVendor, $overdueByVendor, $costByVendor, $windowDays) {
            $resolved = $resolvedByVendor->get($vendor->id) ?? collect();
            $withDue = $resolved->filter(fn (Ticket $t) => $t->resolution_due_at !== null);
            $withDueCount = $withDue->count();
            $withinSla = $withDue->filter(fn (Ticket $t) => $t->resolved_at->lessThanOrEqualTo($t->resolution_due_at))->count();
            $resolvedCount = $resolved->count();
            $costTotal = (int) ($costByVendor[$vendor->id] ?? 0);

            return [
                'vendor_id' => $vendor->id,
                'name' => $vendor->name,
                'window_days' => $windowDays,
                'resolved_count' => $resolvedCount,
                'with_due' => $withDueCount,
                'within_sla' => $withinSla,
                'within_sla_pct' => $withDueCount > 0 ? round($withinSla / $withDueCount * 100, 1) : null,
                'avg_resolution_hours' => $resolvedCount > 0
                    ? round($resolved->avg(fn (Ticket $t) => $t->created_at->diffInHours($t->resolved_at)), 1)
                    : null,
                'open_overdue' => (int) ($overdueByVendor[$vendor->id] ?? 0),
                'cost_total_cents' => $costTotal,
                'cost_per_ticket_cents' => $resolvedCount > 0 ? (int) round($costTotal / $resolvedCount) : null,
            ];
        })->all();
    }
}
