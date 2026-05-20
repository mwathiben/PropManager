<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\AttributionTouchpoint;
use App\Models\Experiment;
use App\Models\ProductEvent;
use App\Services\Growth\AttributionModelService;
use App\Services\Growth\ChurnService;
use App\Services\Growth\FunnelRollupService;
use App\Services\Growth\NpsScoreService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-56 DASHBOARDS-1: super-admin growth-attribution dashboard.
 *
 * Four cards surface the cycle's four analyses:
 *   - attribution_summary  : top-3 channels per model, last 30d
 *   - funnel_sankey        : 90-day sankey payload (ops-wide)
 *   - cohort_by_source     : 6 months of partitioned retention
 *   - experiments_auto_promoted : last 10 concluded experiments
 */
class OpsGrowthAttributionController extends Controller
{
    public function index(
        AttributionModelService $attribution,
        FunnelRollupService $funnel,
        ChurnService $churn,
        NpsScoreService $nps,
    ): Response {
        return Inertia::render('Ops/Growth/Attribution', [
            'attribution_summary' => $this->buildAttributionSummary($attribution),
            'funnel_sankey' => $funnel->computeSankeyPayload(landlordId: null, days: 90),
            'cohort_by_source' => $churn->cohortsBySource(6),
            'experiments_auto_promoted' => $this->buildAutoPromotedTimeline(),
            // Phase-66 GROWTH-OBSERVABILITY-3: platform NPS summary card.
            'nps' => $nps->compute(null, 90),
        ]);
    }

    /**
     * @return array<string, array<int, array{channel: string, credit_pct: float}>>
     */
    private function buildAttributionSummary(AttributionModelService $attribution): array
    {
        $recentUserIds = AttributionTouchpoint::query()
            ->where('touched_at', '>=', now()->subDays(30))
            ->distinct()
            ->pluck('user_id');

        $aggregate = [];
        foreach (AttributionModelService::ALL_MODELS as $model) {
            $aggregate[$model] = [];
        }

        foreach ($recentUserIds as $userId) {
            $perUser = $attribution->computeForUser((int) $userId, now());
            foreach ($perUser as $model => $channels) {
                foreach ($channels as $channel => $creditPct) {
                    $aggregate[$model][$channel] = ($aggregate[$model][$channel] ?? 0.0) + $creditPct;
                }
            }
        }

        $summary = [];
        foreach ($aggregate as $model => $channels) {
            arsort($channels);
            $top = array_slice($channels, 0, 3, preserve_keys: true);
            $total = array_sum($channels);
            $rows = [];
            foreach ($top as $channel => $credit) {
                $rows[] = [
                    'channel' => $channel,
                    'credit_pct' => $total > 0 ? round(($credit / $total) * 100, 2) : 0.0,
                ];
            }
            $summary[$model] = $rows;
        }

        return $summary;
    }

    /**
     * @return array<int, array{experiment_key: string, winning_variant_key: ?string, chi_p: ?float, bayes_posterior: ?float, ended_at: ?string}>
     */
    private function buildAutoPromotedTimeline(): array
    {
        $concluded = Experiment::query()
            ->where('status', Experiment::STATUS_CONCLUDED)
            ->orderByDesc('ends_at')
            ->limit(10)
            ->get(['experiment_key', 'winning_variant_key', 'ends_at']);

        if ($concluded->isEmpty()) {
            return [];
        }

        $events = ProductEvent::query()->withoutGlobalScopes()
            ->where('event_name', 'experiment.concluded')
            ->whereIn('properties->experiment_key', $concluded->pluck('experiment_key'))
            ->get()
            ->keyBy(fn ($e) => $e->properties['experiment_key'] ?? '');

        return $concluded->map(function (Experiment $exp) use ($events) {
            $event = $events->get($exp->experiment_key);

            return [
                'experiment_key' => $exp->experiment_key,
                'winning_variant_key' => $exp->winning_variant_key,
                'chi_p' => $event?->properties['chi_p'] ?? null,
                'bayes_posterior' => $event?->properties['bayes_posterior'] ?? null,
                'ended_at' => $exp->ends_at?->toIso8601String(),
            ];
        })->all();
    }
}
