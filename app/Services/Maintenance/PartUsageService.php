<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Part;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase-75 PARTS-PREDICT-1: estimate how fast a part is consumed from the
 * ticket_parts pivot, so reorder timing can account for supplier lead time.
 *
 * Rate = sum(ticket_parts.qty_used recorded in the window) / windowDays.
 * Always landlord-scoped via the tickets join (ticket_parts has no
 * landlord_id of its own).
 */
class PartUsageService
{
    public const DEFAULT_WINDOW_DAYS = 90;

    public function dailyRate(Part $part, int $windowDays = self::DEFAULT_WINDOW_DAYS): float
    {
        $windowDays = max(1, $windowDays);

        $used = (int) DB::table('ticket_parts')
            ->join('tickets', 'tickets.id', '=', 'ticket_parts.ticket_id')
            ->where('ticket_parts.part_id', $part->id)
            ->where('tickets.landlord_id', $part->landlord_id)
            ->where('ticket_parts.recorded_at', '>=', Carbon::now()->subDays($windowDays))
            ->sum('ticket_parts.qty_used');

        return $used / $windowDays;
    }

    /**
     * Batched daily-rate lookup keyed by part_id, scoped to one landlord.
     * Parts not present in the window default to 0.0.
     *
     * @param  Collection<int, Part>  $parts
     * @return array<int, float>
     */
    public function dailyRatesFor(int $landlordId, Collection $parts, int $windowDays = self::DEFAULT_WINDOW_DAYS): array
    {
        $windowDays = max(1, $windowDays);

        $partIds = $parts->pluck('id')->all();

        $rates = array_fill_keys($partIds, 0.0);

        if ($partIds === []) {
            return $rates;
        }

        $sums = DB::table('ticket_parts')
            ->join('tickets', 'tickets.id', '=', 'ticket_parts.ticket_id')
            ->whereIn('ticket_parts.part_id', $partIds)
            ->where('tickets.landlord_id', $landlordId)
            ->where('ticket_parts.recorded_at', '>=', Carbon::now()->subDays($windowDays))
            ->groupBy('ticket_parts.part_id')
            ->selectRaw('ticket_parts.part_id as part_id, SUM(ticket_parts.qty_used) as used')
            ->pluck('used', 'part_id');

        foreach ($sums as $partId => $used) {
            $rates[(int) $partId] = (int) $used / $windowDays;
        }

        return $rates;
    }
}
