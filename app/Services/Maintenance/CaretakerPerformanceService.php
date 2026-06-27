<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase-80 CARETAKER-PERF-1: landlord-side caretaker comparison. Mirrors
 * VendorPerformanceService (resolution-within-SLA = resolved_at <=
 * resolution_due_at) but keyed by tickets.assigned_to over the landlord's
 * caretakers, plus first-response time, water readings recorded, and
 * escalations raised. Batched (no per-caretaker query / no N+1); landlord-scoped.
 */
class CaretakerPerformanceService
{
    /**
     * @return list<array{
     *   caretaker_id:int, name:string, window_days:int, resolved_count:int,
     *   with_due:int, within_sla:int, within_sla_pct:?float,
     *   avg_resolution_hours:?float, avg_first_response_hours:?float,
     *   open_count:int, open_overdue:int, water_readings_recorded:int,
     *   escalations_raised:int
     * }>
     */
    public function forLandlord(int $landlordId, int $windowDays = 90): array
    {
        $windowDays = max(1, $windowDays);
        $since = now()->subDays($windowDays);

        $caretakers = User::query()
            ->where('landlord_id', $landlordId)
            ->where('role', 'caretaker')
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($caretakers->isEmpty()) {
            return [];
        }

        $ids = $caretakers->pluck('id')->all();
        $aggregates = $this->fetchAggregates($landlordId, $ids, $since);

        return $caretakers->map(function (User $caretaker) use ($aggregates, $windowDays) {
            return $this->buildCaretakerRow($caretaker, $aggregates, $windowDays);
        })->all();
    }

    /**
     * @param  array<int>  $ids
     * @return array{
     *   resolvedByCaretaker: Collection,
     *   openByCaretaker: Collection,
     *   overdueByCaretaker: Collection,
     *   escalationsByCaretaker: Collection,
     *   waterByCaretaker: Collection
     * }
     */
    private function fetchAggregates(int $landlordId, array $ids, \Carbon\Carbon $since): array
    {
        $resolvedByCaretaker = Ticket::query()
            ->where('landlord_id', $landlordId)
            ->whereIn('assigned_to', $ids)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $since)
            ->get(['assigned_to', 'created_at', 'resolved_at', 'resolution_due_at', 'first_response_at'])
            ->groupBy('assigned_to');

        $openByCaretaker = Ticket::query()
            ->where('landlord_id', $landlordId)
            ->whereIn('assigned_to', $ids)
            ->open()
            ->get(['assigned_to'])
            ->countBy('assigned_to');

        $overdueByCaretaker = Ticket::query()
            ->where('landlord_id', $landlordId)
            ->whereIn('assigned_to', $ids)
            ->breachedResolutionSla()
            ->get(['assigned_to'])
            ->countBy('assigned_to');

        $escalationsByCaretaker = Ticket::query()
            ->where('landlord_id', $landlordId)
            ->whereIn('escalated_by', $ids)
            ->where('escalated_at', '>=', $since)
            ->get(['escalated_by'])
            ->countBy('escalated_by');

        $waterByCaretaker = DB::table('water_readings')
            ->where('landlord_id', $landlordId)
            ->whereIn('recorded_by', $ids)
            ->where('status', 'approved')
            ->where('created_at', '>=', $since)
            ->groupBy('recorded_by')
            ->selectRaw('recorded_by, COUNT(*) as total')
            ->pluck('total', 'recorded_by');

        return compact(
            'resolvedByCaretaker',
            'openByCaretaker',
            'overdueByCaretaker',
            'escalationsByCaretaker',
            'waterByCaretaker'
        );
    }

    /**
     * @param  array{
     *   resolvedByCaretaker: Collection,
     *   openByCaretaker: Collection,
     *   overdueByCaretaker: Collection,
     *   escalationsByCaretaker: Collection,
     *   waterByCaretaker: Collection
     * }  $aggregates
     * @return array{
     *   caretaker_id:int, name:string, window_days:int, resolved_count:int,
     *   with_due:int, within_sla:int, within_sla_pct:?float,
     *   avg_resolution_hours:?float, avg_first_response_hours:?float,
     *   open_count:int, open_overdue:int, water_readings_recorded:int,
     *   escalations_raised:int
     * }
     */
    private function buildCaretakerRow(User $caretaker, array $aggregates, int $windowDays): array
    {
        $resolved = $aggregates['resolvedByCaretaker']->get($caretaker->id) ?? collect();
        $withDue = $resolved->filter(fn (Ticket $t) => $t->resolution_due_at !== null);
        $withDueCount = $withDue->count();
        $withinSla = $withDue->filter(fn (Ticket $t) => $t->resolved_at->lessThanOrEqualTo($t->resolution_due_at))->count();
        $resolvedCount = $resolved->count();
        $withResponse = $resolved->filter(fn (Ticket $t) => $t->first_response_at !== null);

        return [
            'caretaker_id' => $caretaker->id,
            'name' => $caretaker->name,
            'window_days' => $windowDays,
            'resolved_count' => $resolvedCount,
            'with_due' => $withDueCount,
            'within_sla' => $withinSla,
            'within_sla_pct' => $withDueCount > 0 ? round($withinSla / $withDueCount * 100, 1) : null,
            'avg_resolution_hours' => $resolvedCount > 0
                ? round($resolved->avg(fn (Ticket $t) => $t->created_at->diffInHours($t->resolved_at)), 1)
                : null,
            'avg_first_response_hours' => $withResponse->count() > 0
                ? round($withResponse->avg(fn (Ticket $t) => $t->created_at->diffInHours($t->first_response_at)), 1)
                : null,
            'open_count' => (int) ($aggregates['openByCaretaker'][$caretaker->id] ?? 0),
            'open_overdue' => (int) ($aggregates['overdueByCaretaker'][$caretaker->id] ?? 0),
            'water_readings_recorded' => (int) ($aggregates['waterByCaretaker'][$caretaker->id] ?? 0),
            'escalations_raised' => (int) ($aggregates['escalationsByCaretaker'][$caretaker->id] ?? 0),
        ];
    }
}
