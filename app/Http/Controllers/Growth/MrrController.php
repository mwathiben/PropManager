<?php

declare(strict_types=1);

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\MrrSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Phase-34 GROWTH-MRR-3: super_admin-only MRR trend endpoint.
 *
 * Returns last N days of mrr_snapshots aggregated total + per-plan
 * breakdown + the day-over-day waterfall components. Feeds the
 * operator Grafana dashboard / leadership weekly review.
 */
class MrrController extends Controller
{
    public function trend(Request $request): JsonResponse
    {
        $days = max(7, min(365, (int) $request->input('days', 90)));
        $since = Carbon::today()->subDays($days);

        $rows = MrrSnapshot::query()
            ->with('plan:id,slug,name')
            ->where('day', '>=', $since->toDateString())
            ->orderBy('day')
            ->get();

        $byDay = [];
        foreach ($rows as $row) {
            $key = $row->day->format('Y-m-d');
            $byDay[$key] ??= [
                'day' => $key,
                'mrr_kes_total' => 0.0,
                'active_subscriptions_total' => 0,
                'new_mrr_kes_total' => 0.0,
                'expansion_mrr_kes_total' => 0.0,
                'contraction_mrr_kes_total' => 0.0,
                'churned_mrr_kes_total' => 0.0,
                'by_plan' => [],
            ];
            $byDay[$key]['mrr_kes_total'] += (float) $row->mrr_kes;
            $byDay[$key]['active_subscriptions_total'] += (int) $row->active_subscriptions;
            $byDay[$key]['new_mrr_kes_total'] += (float) $row->new_mrr_kes;
            $byDay[$key]['expansion_mrr_kes_total'] += (float) $row->expansion_mrr_kes;
            $byDay[$key]['contraction_mrr_kes_total'] += (float) $row->contraction_mrr_kes;
            $byDay[$key]['churned_mrr_kes_total'] += (float) $row->churned_mrr_kes;
            $byDay[$key]['by_plan'][] = [
                'plan_slug' => $row->plan?->slug,
                'plan_name' => $row->plan?->name,
                'mrr_kes' => (float) $row->mrr_kes,
                'active_subscriptions' => (int) $row->active_subscriptions,
            ];
        }

        return response()->json([
            'window_days' => $days,
            'days' => array_values($byDay),
        ]);
    }
}
