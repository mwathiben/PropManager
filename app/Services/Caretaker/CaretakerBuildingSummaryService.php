<?php

declare(strict_types=1);

namespace App\Services\Caretaker;

use App\Models\Building;
use App\Models\CaretakerAssignment;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Phase-77 CARETAKER-CONTEXT-1: per-building scope for a caretaker — unit count,
 * occupancy, and open-ticket load on each building they manage (or are being
 * asked to manage). Strictly scoped via building.landlord_id === the caretaker's
 * landlord; counts are batched (no N+1).
 */
class CaretakerBuildingSummaryService
{
    /**
     * @param  list<string>  $statuses  CaretakerAssignment statuses to include
     * @return list<array{building_id:int, name:string, unit_count:int, occupied_count:int, open_ticket_count:int}>
     */
    public function forCaretaker(User $caretaker, array $statuses = [CaretakerAssignment::STATUS_ACCEPTED]): array
    {
        $buildingIds = CaretakerAssignment::query()
            ->where('caretaker_id', $caretaker->id)
            ->whereIn('status', $statuses)
            ->pluck('building_id');

        return $this->forBuildings($caretaker, $buildingIds);
    }

    /**
     * @param  Collection<int, int>|list<int>  $buildingIds
     * @return list<array{building_id:int, name:string, unit_count:int, occupied_count:int, open_ticket_count:int}>
     */
    public function forBuildings(User $caretaker, Collection|array $buildingIds): array
    {
        $buildings = Building::query()
            ->whereIn('id', $buildingIds)
            ->where('landlord_id', $caretaker->landlord_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($buildings->isEmpty()) {
            return [];
        }

        $ids = $buildings->pluck('id');

        $units = Unit::query()
            ->whereIn('building_id', $ids)
            ->groupBy('building_id')
            ->selectRaw('building_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as occupied', ['occupied'])
            ->get()
            ->keyBy('building_id');

        $openTickets = Ticket::query()
            ->whereIn('building_id', $ids)
            ->open()
            ->groupBy('building_id')
            ->selectRaw('building_id, COUNT(*) as cnt')
            ->get()
            ->keyBy('building_id');

        return $buildings->map(fn (Building $b) => [
            'building_id' => $b->id,
            'name' => $b->name,
            'unit_count' => (int) ($units[$b->id]->total ?? 0),
            'occupied_count' => (int) ($units[$b->id]->occupied ?? 0),
            'open_ticket_count' => (int) ($openTickets[$b->id]->cnt ?? 0),
        ])->all();
    }
}
