<?php

declare(strict_types=1);

namespace App\Services\Vendors;

use App\Models\Ticket;
use App\Models\Vendor;

/**
 * Phase-70 SLA-DASHBOARD-1: a vendor's own SLA performance over a window,
 * computed from their tickets' timestamps. Resolution-within-SLA =
 * resolved_at <= resolution_due_at. Everything scopes to the vendor.
 */
class VendorSlaService
{
    /**
     * @return array{
     *   window_days:int, total_resolved:int, with_due:int, within_sla:int,
     *   breached:int, within_sla_pct:?float, avg_resolution_hours:?float, open_overdue:int
     * }
     */
    public function forVendor(Vendor $vendor, int $windowDays = 90): array
    {
        $windowDays = max(1, $windowDays);
        $since = now()->subDays($windowDays);

        $resolved = Ticket::query()
            ->where('vendor_id', $vendor->id)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $since)
            ->get(['created_at', 'resolved_at', 'resolution_due_at']);

        $withDue = $resolved->filter(fn (Ticket $t) => $t->resolution_due_at !== null);
        $withinSla = $withDue->filter(fn (Ticket $t) => $t->resolved_at->lessThanOrEqualTo($t->resolution_due_at))->count();
        $withDueCount = $withDue->count();

        $avgHours = $resolved->isNotEmpty()
            ? round($resolved->avg(fn (Ticket $t) => $t->created_at->diffInHours($t->resolved_at)), 1)
            : null;

        return [
            'window_days' => $windowDays,
            'total_resolved' => $resolved->count(),
            'with_due' => $withDueCount,
            'within_sla' => $withinSla,
            'breached' => $withDueCount - $withinSla,
            'within_sla_pct' => $withDueCount > 0 ? round($withinSla / $withDueCount * 100, 1) : null,
            'avg_resolution_hours' => $avgHours,
            'open_overdue' => Ticket::query()->where('vendor_id', $vendor->id)->breachedResolutionSla()->count(),
        ];
    }
}
