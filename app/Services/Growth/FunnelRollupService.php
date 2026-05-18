<?php

declare(strict_types=1);

namespace App\Services\Growth;

use App\Models\ProductEvent;

/**
 * Phase-56 FUNNEL-SANKEY-2: rollup product_events 'funnel.*' into a
 * Sankey-compatible payload.
 *
 * For each pair of adjacent stages we count:
 *   - users who fired BOTH the source AND the target stage   → continuation link
 *   - users who fired only the source                        → drop-off link
 *
 * Drop-off nodes are synthetic 'dropped_at_<target>' identifiers so the
 * sankey totals stay balanced at every stage boundary.
 *
 * Scope:
 *   - landlordId === null    → ops mode (all landlords; product_events
 *                              read with withoutGlobalScopes since the
 *                              caller is super_admin).
 *   - landlordId !== null    → scoped to one landlord's funnel.
 */
class FunnelRollupService
{
    public function computeSankeyPayload(?int $landlordId = null, int $days = 90): array
    {
        $stages = FunnelStage::ordered();
        $since = now()->subDays($days);

        // Phase-57 READ-REPLICAS-3: heavy aggregate, eventual consistency OK.
        $query = ProductEvent::query()->withoutGlobalScopes()->readOnly()
            ->where('created_at', '>=', $since)
            ->whereIn('event_name', array_map(fn (FunnelStage $s) => $s->eventName(), $stages));

        if ($landlordId !== null) {
            $query->where('landlord_id', $landlordId);
        }

        $rows = $query
            ->select(['user_id', 'event_name'])
            ->distinct()
            ->get();

        $userStages = [];
        foreach ($rows as $row) {
            if ($row->user_id === null) {
                continue;
            }
            $stage = str_replace('funnel.', '', $row->event_name);
            $userStages[$row->user_id][$stage] = true;
        }

        $nodes = [];
        $links = [];
        $stageCounts = [];
        foreach ($stages as $stage) {
            $count = 0;
            foreach ($userStages as $user => $stamps) {
                if (isset($stamps[$stage->value])) {
                    $count++;
                }
            }
            $stageCounts[$stage->value] = $count;
            $nodes[] = [
                'id' => $stage->value,
                'label' => $stage->label(),
                'count' => $count,
            ];
        }

        for ($i = 0; $i < count($stages) - 1; $i++) {
            $source = $stages[$i];
            $target = $stages[$i + 1];
            $continued = 0;
            $droppedOff = 0;

            foreach ($userStages as $user => $stamps) {
                if (! isset($stamps[$source->value])) {
                    continue;
                }
                if (isset($stamps[$target->value])) {
                    $continued++;
                } else {
                    $droppedOff++;
                }
            }

            $links[] = [
                'source' => $source->value,
                'target' => $target->value,
                'value' => $continued,
            ];
            if ($droppedOff > 0) {
                $droppedNodeId = 'dropped_at_'.$target->value;
                $nodes[] = [
                    'id' => $droppedNodeId,
                    'label' => 'Dropped before '.$target->label(),
                    'count' => $droppedOff,
                ];
                $links[] = [
                    'source' => $source->value,
                    'target' => $droppedNodeId,
                    'value' => $droppedOff,
                ];
            }
        }

        return [
            'nodes' => $nodes,
            'links' => $links,
            'window_days' => $days,
        ];
    }
}
