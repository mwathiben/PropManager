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
        $userStages = $this->fetchUserStages($landlordId, $days, $stages);

        [$nodes, $stageCounts] = $this->buildStageNodes($stages, $userStages);
        $links = $this->buildLinks($stages, $userStages, $nodes);

        return [
            'nodes' => $nodes,
            'links' => $links,
            'window_days' => $days,
        ];
    }

    /**
     * Query distinct (user_id, event_name) rows and index them as
     * $userStages[userId][stageValue] = true.
     */
    private function fetchUserStages(?int $landlordId, int $days, array $stages): array
    {
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

        return $userStages;
    }

    /**
     * Build the node list for all real stages and return [nodes, stageCounts].
     *
     * @param  FunnelStage[]  $stages
     * @return array{0: array<int, array{id: string, label: string, count: int}>, 1: array<string, int>}
     */
    private function buildStageNodes(array $stages, array $userStages): array
    {
        $nodes = [];
        $stageCounts = [];

        foreach ($stages as $stage) {
            $count = $this->countUsersAtStage($stage->value, $userStages);
            $stageCounts[$stage->value] = $count;
            $nodes[] = [
                'id' => $stage->value,
                'label' => $stage->label(),
                'count' => $count,
            ];
        }

        return [$nodes, $stageCounts];
    }

    /** Count how many users reached a given stage. */
    private function countUsersAtStage(string $stageValue, array $userStages): int
    {
        $count = 0;
        foreach ($userStages as $stamps) {
            if (isset($stamps[$stageValue])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Build links between adjacent stage pairs, appending drop-off nodes to
     * $nodes in-place when users dropped before reaching the target stage.
     *
     * @param  FunnelStage[]  $stages
     */
    private function buildLinks(array $stages, array $userStages, array &$nodes): array
    {
        $links = [];

        for ($i = 0; $i < count($stages) - 1; $i++) {
            $source = $stages[$i];
            $target = $stages[$i + 1];

            [$continued, $droppedOff] = $this->countTransition($source->value, $target->value, $userStages);

            $links[] = [
                'source' => $source->value,
                'target' => $target->value,
                'value' => $continued,
            ];

            if ($droppedOff > 0) {
                $this->appendDropOffNode($nodes, $links, [
                    'source' => $source->value,
                    'target' => $target,
                    'count' => $droppedOff,
                ]);
            }
        }

        return $links;
    }

    /**
     * Count users who continued from source → target and those who dropped off.
     *
     * @return array{0: int, 1: int} [continued, droppedOff]
     */
    private function countTransition(string $sourceValue, string $targetValue, array $userStages): array
    {
        $continued = 0;
        $droppedOff = 0;

        foreach ($userStages as $stamps) {
            if (! isset($stamps[$sourceValue])) {
                continue;
            }
            if (isset($stamps[$targetValue])) {
                $continued++;
            } else {
                $droppedOff++;
            }
        }

        return [$continued, $droppedOff];
    }

    /**
     * Append a synthetic drop-off node and its link to the running arrays.
     *
     * @param  array{source: string, target: FunnelStage, count: int}  $dropOff
     */
    private function appendDropOffNode(array &$nodes, array &$links, array $dropOff): void
    {
        $droppedNodeId = 'dropped_at_'.$dropOff['target']->value;
        $nodes[] = [
            'id' => $droppedNodeId,
            'label' => 'Dropped before '.$dropOff['target']->label(),
            'count' => $dropOff['count'],
        ];
        $links[] = [
            'source' => $dropOff['source'],
            'target' => $droppedNodeId,
            'value' => $dropOff['count'],
        ];
    }
}
